<?php

use App\Domain\Queues\LoadBalancedStrategy;
use App\Domain\Queues\SkillBasedStrategy;
use App\Enums\LeadState;
use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;

it('assigns the agent with fewest open leads', function () {
    $team = Team::factory()->create();
    $queue = CrmQueue::factory()->create(['team_id' => $team->id, 'max_concurrency_per_agent' => 5]);
    $light = User::factory()->create(['team_id' => $team->id, 'role' => 'agent', 'max_concurrency' => 5]);
    $heavy = User::factory()->create(['team_id' => $team->id, 'role' => 'agent', 'max_concurrency' => 5]);

    Lead::factory()->count(3)->create([
        'queue_id' => $queue->id,
        'assigned_agent_id' => $heavy->id,
        'state' => LeadState::Working,
    ]);

    $lead = Lead::factory()->create(['queue_id' => $queue->id, 'state' => LeadState::Queued]);
    $picked = app(LoadBalancedStrategy::class)->pickAgent($queue, $lead);

    expect($picked?->id)->toBe($light->id);
});

it('prefers agents whose tags match the lead', function () {
    $team = Team::factory()->create();
    $queue = CrmQueue::factory()->create(['team_id' => $team->id]);
    $general = User::factory()->create(['team_id' => $team->id, 'role' => 'agent', 'tags' => ['starter']]);
    $enterprise = User::factory()->create(['team_id' => $team->id, 'role' => 'agent', 'tags' => ['enterprise']]);

    $lead = Lead::factory()->create([
        'queue_id' => $queue->id,
        'product_line' => 'enterprise',
        'tags' => ['enterprise'],
        'state' => LeadState::Queued,
    ]);

    $picked = app(SkillBasedStrategy::class)->pickAgent($queue, $lead);

    expect($picked?->id)->toBe($enterprise->id);
});
