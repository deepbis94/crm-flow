<?php

namespace App\Services;

use App\Domain\Leads\LeadStateMachine;
use App\Domain\Queues\AssignmentStrategyFactory;
use App\Enums\LeadState;
use App\Exceptions\LeadAlreadyClaimedException;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class ClaimService
{
    public function __construct(
        private readonly LeadLock $locks,
        private readonly LeadStateMachine $states,
        private readonly AssignmentStrategyFactory $strategies,
    ) {
    }

    public function claim(Lead $lead, User $agent): Lead
    {
        if ($lead->state !== LeadState::Queued) {
            throw new LeadAlreadyClaimedException('Lead is not available to claim.');
        }

        $queue = $lead->queue;
        if ($queue) {
            $open = Lead::query()
                ->where('assigned_agent_id', $agent->id)
                ->whereIn('state', [LeadState::Claimed->value, LeadState::Working->value])
                ->count();
            $cap = min($agent->max_concurrency, $queue->max_concurrency_per_agent);
            if ($open >= $cap) {
                throw new LeadAlreadyClaimedException('Agent is at max concurrency for this queue.');
            }
        }

        $token = $this->locks->acquire($lead->id);
        if ($token === null) {
            throw new LeadAlreadyClaimedException('Lead is locked by another agent.');
        }

        try {
            $claimed = $this->states->transition(
                $lead,
                LeadState::Claimed,
                'agent_claim',
                $agent,
                attributes: [
                    'assigned_agent_id' => $agent->id,
                    'claimed_at' => now(),
                ],
                meta: ['lock_token' => $token],
            );
        } catch (\Throwable $e) {
            $this->locks->release($lead->id, $token);
            throw $e;
        }

        return $claimed;
    }

    public function autoAssign(Lead $lead): ?Lead
    {
        $queue = $lead->queue;
        if (! $queue) {
            return null;
        }

        $agent = $this->strategies->make($queue->assignment_strategy)->pickAgent($queue, $lead);
        if (! $agent) {
            return null;
        }

        try {
            return $this->claim($lead, $agent);
        } catch (LeadAlreadyClaimedException $e) {
            Log::info('auto_assign_skipped', ['lead_id' => $lead->id, 'reason' => $e->getMessage()]);

            return null;
        }
    }

    public function startWorking(Lead $lead, User $agent): Lead
    {
        $queue = $lead->queue;
        $followup = $queue ? now()->addSeconds($queue->followup_sla_seconds) : null;

        return $this->states->transition(
            $lead,
            LeadState::Working,
            'agent_working',
            $agent,
            attributes: [
                'sla_first_touch_due_at' => null,
                'sla_followup_due_at' => $followup,
                'sla_status' => 'ok',
            ],
        );
    }

    public function releaseToQueue(Lead $lead, string $reasonCode, ?User $actor = null): Lead
    {
        $token = $this->locks->token($lead->id);
        $released = $this->states->transition(
            $lead,
            LeadState::Queued,
            $reasonCode,
            $actor,
            attributes: [
                'assigned_agent_id' => null,
                'claimed_at' => null,
            ],
        );

        if ($token) {
            $this->locks->release($lead->id, $token);
        }

        return $released;
    }

    public function releaseExpiredLocks(): int
    {
        $claimed = Lead::query()->where('state', LeadState::Claimed)->get();
        $released = 0;

        foreach ($claimed as $lead) {
            if ($this->locks->token($lead->id) !== null) {
                continue;
            }

            $this->states->transition(
                $lead,
                LeadState::Queued,
                'lock_expired',
                attributes: [
                    'assigned_agent_id' => null,
                    'claimed_at' => null,
                ],
            );
            $released++;
        }

        return $released;
    }
}
