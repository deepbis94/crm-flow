<?php

namespace App\Domain\Queues;

use App\Contracts\AssignmentStrategy;
use App\Enums\LeadState;
use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\User;

final class LoadBalancedStrategy implements AssignmentStrategy
{
    public function name(): string
    {
        return 'load_balanced';
    }

    public function pickAgent(CrmQueue $queue, Lead $lead): ?User
    {
        $agents = User::query()
            ->where('team_id', $queue->team_id)
            ->where('role', 'agent')
            ->orderBy('id')
            ->get();

        $best = null;
        $bestOpen = PHP_INT_MAX;

        foreach ($agents as $agent) {
            $open = Lead::query()
                ->where('assigned_agent_id', $agent->id)
                ->whereIn('state', [LeadState::Claimed->value, LeadState::Working->value])
                ->count();

            $cap = min($agent->max_concurrency, $queue->max_concurrency_per_agent);
            if ($open >= $cap) {
                continue;
            }

            if ($open < $bestOpen) {
                $best = $agent;
                $bestOpen = $open;
            }
        }

        return $best;
    }
}
