<?php

use App\Enums\LeadState;
use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\SlaBreach;
use App\Models\User;
use App\Services\SlaService;

beforeEach(fn () => flushTestRedis());

it('escalates a first-touch SLA breach, re-queues, and records it', function () {
    $queue = CrmQueue::factory()->create([
        'priority_weight' => 100,
        'first_touch_sla_seconds' => 1,
    ]);
    User::factory()->supervisor()->create(['team_id' => $queue->team_id]);
    $agent = User::factory()->create(['team_id' => $queue->team_id]);
    $lead = Lead::factory()->create([
        'queue_id' => $queue->id,
        'state' => LeadState::Claimed,
        'assigned_agent_id' => $agent->id,
        'priority' => 100,
        'sla_first_touch_due_at' => now()->subMinute(),
        'sla_status' => 'ok',
        'version' => 1,
    ]);

    $count = app(SlaService::class)->sweep();

    $fresh = $lead->fresh();
    expect($count)->toBe(1)
        ->and(SlaBreach::query()->where('lead_id', $lead->id)->where('type', 'first_touch')->count())->toBe(1)
        ->and($fresh->sla_status)->toBe('breached')
        ->and($fresh->priority)->toBeGreaterThan(100)
        ->and($fresh->state)->toBe(LeadState::Queued);
});
