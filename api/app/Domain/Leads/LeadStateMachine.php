<?php

namespace App\Domain\Leads;

use App\Enums\LeadState;
use App\Exceptions\ConcurrentLeadTransitionException;
use App\Exceptions\IllegalLeadTransitionException;
use App\Models\Lead;
use App\Models\LeadTransition;
use App\Models\RejectedTransition;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class LeadStateMachine
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $meta
     */
    public function transition(
        Lead $lead,
        LeadState $to,
        string $reasonCode,
        ?User $agent = null,
        array $attributes = [],
        array $meta = [],
    ): Lead {
        $from = $lead->state;

        if (! $from->canTransitionTo($to)) {
            $this->reject($lead, $from, $to, $agent, 'illegal_transition', $meta);
            throw new IllegalLeadTransitionException("Cannot move lead from {$from->value} to {$to->value}.");
        }

        $payload = array_merge($attributes, [
            'state' => $to->value,
            'version' => $lead->version + 1,
            'last_touched_at' => now(),
        ]);

        $updated = Lead::query()
            ->where('id', $lead->id)
            ->where('state', $from->value)
            ->where('version', $lead->version)
            ->update($payload);

        if ($updated === 0) {
            $fresh = $lead->fresh();
            $this->reject(
                $lead,
                $from,
                $to,
                $agent,
                'concurrent_conflict',
                array_merge($meta, [
                    'actual_state' => $fresh?->state?->value,
                    'actual_version' => $fresh?->version,
                ]),
            );
            throw new ConcurrentLeadTransitionException('Lead transition lost a race.');
        }

        LeadTransition::query()->create([
            'lead_id' => $lead->id,
            'from_state' => $from->value,
            'to_state' => $to->value,
            'agent_id' => $agent?->id,
            'reason_code' => $reasonCode,
            'meta' => $meta,
            'created_at' => now(),
        ]);

        Log::info('lead_transition', [
            'lead_id' => $lead->id,
            'from' => $from->value,
            'to' => $to->value,
            'reason' => $reasonCode,
            'agent_id' => $agent?->id,
        ]);

        return $lead->fresh();
    }

    private function reject(
        Lead $lead,
        LeadState $expected,
        LeadState $attempted,
        ?User $agent,
        string $reason,
        array $meta,
    ): void {
        RejectedTransition::query()->create([
            'lead_id' => $lead->id,
            'expected_state' => $expected->value,
            'actual_state' => $lead->fresh()?->state?->value,
            'attempted_to' => $attempted->value,
            'agent_id' => $agent?->id,
            'reason_code' => $reason,
            'meta' => $meta,
            'created_at' => now(),
        ]);

        Log::warning('lead_transition_rejected', [
            'lead_id' => $lead->id,
            'expected' => $expected->value,
            'attempted' => $attempted->value,
            'reason' => $reason,
        ]);
    }
}
