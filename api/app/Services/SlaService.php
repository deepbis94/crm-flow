<?php

namespace App\Services;

use App\Domain\Leads\LeadStateMachine;
use App\Enums\AgentRole;
use App\Enums\LeadState;
use App\Models\Lead;
use App\Models\SlaBreach;
use App\Models\User;
use App\Support\WorkerJobs;
use Illuminate\Support\Facades\Log;

final class SlaService
{
    public function __construct(
        private readonly LeadStateMachine $states,
        private readonly TokenBucket $outbound,
        private readonly ClaimService $claims,
    ) {
    }

    public function sweep(): int
    {
        $now = now();
        $breached = 0;

        $firstTouch = Lead::query()
            ->whereIn('state', [LeadState::Queued->value, LeadState::Claimed->value])
            ->whereNotNull('sla_first_touch_due_at')
            ->where('sla_first_touch_due_at', '<=', $now)
            ->where('sla_status', '!=', 'breached')
            ->get();

        foreach ($firstTouch as $lead) {
            $this->escalate($lead, 'first_touch');
            $breached++;
        }

        $followup = Lead::query()
            ->where('state', LeadState::Working)
            ->whereNotNull('sla_followup_due_at')
            ->where('sla_followup_due_at', '<=', $now)
            ->where('sla_status', '!=', 'breached')
            ->get();

        foreach ($followup as $lead) {
            $this->escalate($lead, 'followup');
            $breached++;
        }

        return $breached;
    }

    public function escalate(Lead $lead, string $type): void
    {
        $supervisor = User::query()
            ->where('team_id', $lead->queue?->team_id)
            ->where('role', AgentRole::Supervisor)
            ->first();

        SlaBreach::query()->create([
            'lead_id' => $lead->id,
            'queue_id' => $lead->queue_id,
            'type' => $type,
            'notified_supervisor_id' => $supervisor?->id,
            'escalated_at' => now(),
            'meta' => ['priority_before' => $lead->priority],
        ]);

        // Single-worker sweep: this annotation is not version-gated. Agent
        // state changes still go through LeadStateMachine; we only stamp
        // sla_status/priority here before the re-queue transition.
        $lead->forceFill([
            'priority' => min(1000, $lead->priority + 200),
            'sla_status' => 'breached',
        ])->save();

        if ($lead->state === LeadState::Claimed || $lead->state === LeadState::Working) {
            try {
                $this->claims->releaseToQueue($lead->fresh(), 'sla_escalation');
            } catch (\Throwable $e) {
                Log::warning('sla_requeue_failed', ['lead_id' => $lead->id, 'err' => $e->getMessage()]);
            }
        }

        if ($this->outbound->allow('outbound:sla-notify', (float) config('crmflow.outbound.refill_per_second'), (int) config('crmflow.outbound.burst'))) {
            WorkerJobs::push('notifications.sla', [
                'lead_id' => $lead->id,
                'type' => $type,
                'supervisor_id' => $supervisor?->id,
            ], 'sla:'.$lead->id.':'.$type.':'.now()->timestamp);
        }

        Log::warning('sla_breached', [
            'lead_id' => $lead->id,
            'type' => $type,
            'supervisor_id' => $supervisor?->id,
        ]);
    }
}
