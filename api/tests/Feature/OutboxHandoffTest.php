<?php

use App\Enums\LeadState;
use App\Enums\OutboxStatus;
use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\OrderHandoffService;
use App\Services\OutboxRelayService;
use Illuminate\Support\Str;

beforeEach(fn () => flushTestRedis());

it('writes the order and outbox event in the same transaction', function () {
    $queue = CrmQueue::factory()->create();
    $agent = User::factory()->create(['team_id' => $queue->team_id]);
    $lead = Lead::factory()->create(['queue_id' => $queue->id, 'state' => LeadState::Queued, 'version' => 1]);

    $claimed = app(ClaimService::class)->claim($lead, $agent);
    $working = app(ClaimService::class)->startWorking($claimed, $agent);
    $order = app(OrderHandoffService::class)->qualify($working, $agent, [
        'sku' => 'pro-annual',
        'name' => 'Pro Annual',
        'amount_minor' => 19900,
    ]);

    $event = OutboxEvent::query()->where('aggregate_id', $order->id)->first();

    expect($lead->fresh()->state)->toBe(LeadState::Qualified)
        ->and($event)->not->toBeNull()
        ->and($event->event_type)->toBe('order.created')
        ->and($event->status)->toBe(OutboxStatus::Pending)
        ->and($order->lead_snapshot['email'])->toBe($lead->email);
});

it('retries failed outbox events until they exhaust attempts', function () {
    $pending = OutboxEvent::query()->create([
        'event_id' => (string) Str::uuid(),
        'aggregate_type' => 'order',
        'aggregate_id' => (string) Str::uuid(),
        'event_type' => 'order.created',
        'payload' => ['retry' => true],
        'status' => OutboxStatus::Failed,
        'attempts' => 3,
    ]);
    OutboxEvent::query()->create([
        'event_id' => (string) Str::uuid(),
        'aggregate_type' => 'order',
        'aggregate_id' => (string) Str::uuid(),
        'event_type' => 'order.created',
        'payload' => ['dead' => true],
        'status' => OutboxStatus::Dead,
        'attempts' => 8,
    ]);

    $published = app(OutboxRelayService::class)->publishPending();

    expect($published)->toBe(1)
        ->and($pending->fresh()->status)->toBe(OutboxStatus::Published)
        ->and(OutboxEvent::query()->where('status', OutboxStatus::Dead)->count())->toBe(1);
});
