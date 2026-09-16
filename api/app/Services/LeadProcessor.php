<?php

namespace App\Services;

use App\Domain\Leads\LeadStateMachine;
use App\Enums\LeadState;
use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\Source;
use Illuminate\Support\Facades\Log;

final class LeadProcessor
{
    public function __construct(private readonly LeadStateMachine $states)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): Lead
    {
        $existing = Lead::query()->where('intake_id', $payload['intake_id'])->first();
        if ($existing) {
            return $existing;
        }

        $source = Source::query()->findOrFail($payload['source_id']);
        $duplicate = $this->findSuspectedDuplicate($payload['email'] ?? null, $payload['phone'] ?? null);
        $queue = $this->resolveQueue($source, $payload['product_line'] ?? null);

        $lead = Lead::query()->create([
            'intake_id' => $payload['intake_id'],
            'source_id' => $source->id,
            'queue_id' => $queue?->id,
            'state' => LeadState::New,
            'name' => $payload['name'],
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'campaign_id' => $payload['campaign_id'] ?? null,
            'form_id' => $payload['form_id'] ?? null,
            'product_line' => $payload['product_line'] ?? null,
            'tags' => $payload['tags'] ?? [],
            'suspected_duplicate_of' => $duplicate?->id,
            'priority' => $queue?->priority_weight ?? 100,
            'sla_status' => 'ok',
        ]);

        if ($duplicate) {
            Log::info('lead_duplicate_flagged', [
                'lead_id' => $lead->id,
                'duplicate_of' => $duplicate->id,
            ]);
        }

        if ($queue) {
            $due = now()->addSeconds($queue->first_touch_sla_seconds);
            $lead = $this->states->transition(
                $lead,
                LeadState::Queued,
                'intake_enqueued',
                attributes: [
                    'sla_first_touch_due_at' => $due,
                    'priority' => $queue->priority_weight,
                ],
            );
        }

        return $lead;
    }

    private function findSuspectedDuplicate(?string $email, ?string $phone): ?Lead
    {
        if (! $email && ! $phone) {
            return null;
        }

        return Lead::query()
            ->where(function ($q) use ($email, $phone) {
                if ($email) {
                    $q->orWhere('email', $email);
                }
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
            })
            ->orderBy('created_at')
            ->first();
    }

    private function resolveQueue(Source $source, ?string $productLine): ?CrmQueue
    {
        $query = CrmQueue::query()->where('is_active', true);

        $match = (clone $query)
            ->where('source_id', $source->id)
            ->when($productLine, fn ($q) => $q->where('product_line', $productLine))
            ->orderByDesc('priority_weight')
            ->first();

        if ($match) {
            return $match;
        }

        return $query->orderByDesc('priority_weight')->first();
    }
}
