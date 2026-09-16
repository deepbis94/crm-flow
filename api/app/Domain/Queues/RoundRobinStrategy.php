<?php

namespace App\Domain\Queues;

use App\Contracts\AssignmentStrategy;
use App\Enums\LeadState;
use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

final class RoundRobinStrategy implements AssignmentStrategy
{
    public function name(): string
    {
        return 'round_robin';
    }

    public function pickAgent(CrmQueue $queue, Lead $lead): ?User
    {
        $agents = $this->eligible($queue);
        if ($agents->isEmpty()) {
            return null;
        }

        $index = (int) Redis::incr("rr:queue:{$queue->id}");
        $offset = ($index - 1) % $agents->count();

        return $agents->values()->get($offset);
    }

    private function eligible(CrmQueue $queue)
    {
        return User::query()
            ->where('team_id', $queue->team_id)
            ->where('role', 'agent')
            ->orderBy('id')
            ->get()
            ->filter(fn (User $agent) => $this->underConcurrency($agent, $queue));
    }

    private function underConcurrency(User $agent, CrmQueue $queue): bool
    {
        $open = Lead::query()
            ->where('assigned_agent_id', $agent->id)
            ->whereIn('state', [LeadState::Claimed->value, LeadState::Working->value])
            ->count();

        $cap = min($agent->max_concurrency, $queue->max_concurrency_per_agent);

        return $open < $cap;
    }
}
