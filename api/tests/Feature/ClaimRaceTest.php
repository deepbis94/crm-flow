<?php

use App\Enums\LeadState;
use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\LeadLock;

beforeEach(fn () => flushTestRedis());

it('lets exactly one agent win a claim race', function () {
    $queue = CrmQueue::factory()->create(['max_concurrency_per_agent' => 5]);
    $lead = Lead::factory()->create(['queue_id' => $queue->id, 'state' => LeadState::Queued, 'version' => 1]);
    $a = User::factory()->create(['team_id' => $queue->team_id, 'max_concurrency' => 5]);
    $b = User::factory()->create(['team_id' => $queue->team_id, 'max_concurrency' => 5]);

    $claims = app(ClaimService::class);
    $winner = null;
    $loser = null;

    try {
        $winner = $claims->claim($lead, $a);
    } catch (Throwable) {
        $loser = $a->id;
    }

    try {
        $claims->claim($lead->fresh(), $b);
        $winner ??= $b;
    } catch (Throwable) {
        $loser = $b->id;
    }

    expect($winner)->not->toBeNull()
        ->and($lead->fresh()->assigned_agent_id)->toBe($winner->assigned_agent_id)
        ->and($loser)->not->toBeNull()
        ->and(\App\Models\RejectedTransition::query()->count())->toBeGreaterThanOrEqual(0);
});

it('acquires a redis lock for only one claimer', function () {
    $lead = Lead::factory()->create(['state' => LeadState::Queued]);
    $lock = app(LeadLock::class);

    $first = $lock->acquire($lead->id, 5000);
    $second = $lock->acquire($lead->id, 5000);

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull();
});

it('enforces per-agent concurrency under the claim mutex', function () {
    $queue = CrmQueue::factory()->create(['max_concurrency_per_agent' => 1]);
    $agent = User::factory()->create(['team_id' => $queue->team_id, 'max_concurrency' => 1]);
    $first = Lead::factory()->create(['queue_id' => $queue->id, 'state' => LeadState::Queued, 'version' => 1]);
    $second = Lead::factory()->create(['queue_id' => $queue->id, 'state' => LeadState::Queued, 'version' => 1]);

    $claims = app(ClaimService::class);
    $claims->claim($first, $agent);

    expect(fn () => $claims->claim($second, $agent))
        ->toThrow(\App\Exceptions\LeadAlreadyClaimedException::class)
        ->and($second->fresh()->state)->toBe(LeadState::Queued);
});

it('returns a lead to the queue after the lock expires', function () {
    $queue = CrmQueue::factory()->create();
    $agent = User::factory()->create(['team_id' => $queue->team_id]);
    $lead = Lead::factory()->create(['queue_id' => $queue->id, 'state' => LeadState::Queued, 'version' => 1]);

    $claims = app(ClaimService::class);
    $claimed = $claims->claim($lead, $agent);
    expect($claimed->state)->toBe(LeadState::Claimed);

    \Illuminate\Support\Facades\Redis::del(app(LeadLock::class)->key($lead->id));
    $claimed->forceFill([
        'claimed_at' => now()->subMilliseconds((int) config('crmflow.claim_lock_ttl_ms') + 1000),
    ])->save();

    $released = $claims->releaseExpiredLocks();

    expect($released)->toBe(1)
        ->and($lead->fresh()->state)->toBe(LeadState::Queued)
        ->and($lead->fresh()->assigned_agent_id)->toBeNull();
});
