<?php

namespace App\Domain\Queues;

use App\Contracts\AssignmentStrategy;
use App\Enums\LeadState;
use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\User;

final class SkillBasedStrategy implements AssignmentStrategy
{
    public function name(): string
    {
        return 'skill_based';
    }

    public function pickAgent(CrmQueue $queue, Lead $lead): ?User
    {
        $needed = array_values(array_filter([
            $lead->product_line,
            ...($lead->tags ?? []),
        ]));

        $agents = User::query()
            ->where('team_id', $queue->team_id)
            ->where('role', 'agent')
            ->orderBy('id')
            ->get();

        $scored = $agents
            ->map(function (User $agent) use ($queue, $needed) {
                $open = Lead::query()
                    ->where('assigned_agent_id', $agent->id)
                    ->whereIn('state', [LeadState::Claimed->value, LeadState::Working->value])
                    ->count();
                $cap = min($agent->max_concurrency, $queue->max_concurrency_per_agent);
                if ($open >= $cap) {
                    return null;
                }

                $tags = $agent->tags ?? [];
                $overlap = count(array_intersect($needed, $tags));

                return ['agent' => $agent, 'overlap' => $overlap, 'open' => $open];
            })
            ->filter()
            ->sortBy([
                ['overlap', 'desc'],
                ['open', 'asc'],
            ]);

        $best = $scored->first();

        return $best['agent'] ?? null;
    }
}
