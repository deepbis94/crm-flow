<?php

use App\Enums\LeadState;
use App\Enums\PaymentStatus;
use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\OrderHandoffService;
use App\Services\PaymentEventConsumer;

beforeEach(fn () => flushTestRedis());

it('acks duplicate payment webhooks with zero side effects', function () {
    $queue = CrmQueue::factory()->create();
    $agent = User::factory()->create(['team_id' => $queue->team_id]);
    $lead = Lead::factory()->create(['queue_id' => $queue->id, 'state' => LeadState::Queued, 'version' => 1]);

    $claimed = app(ClaimService::class)->claim($lead, $agent);
    $working = app(ClaimService::class)->startWorking($claimed, $agent);
    $order = app(OrderHandoffService::class)->qualify($working, $agent, [
        'sku' => 'starter-monthly',
        'name' => 'Starter Monthly',
        'amount_minor' => 4900,
    ]);

    $payload = [
        'provider' => 'checkouthub',
        'event_id' => 'evt_dup_1',
        'type' => 'payment.succeeded',
        'order_id' => $order->id,
        'payload' => ['amount_minor' => 4900],
    ];

    $first = $this->postJson('/api/v1/webhooks/payments', $payload, [
        'X-Webhook-Secret' => 'crmflow-payment-whsec',
    ]);
    $first->assertOk()->assertJson(['status' => 'accepted', 'applied' => true]);

    $second = $this->postJson('/api/v1/webhooks/payments', $payload, [
        'X-Webhook-Secret' => 'crmflow-payment-whsec',
    ]);
    $second->assertOk()->assertJson(['status' => 'duplicate', 'applied' => false]);

    expect(PaymentEvent::query()->count())->toBe(1)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Succeeded)
        ->and($order->fresh()->version)->toBe(2);
});

it('does not apply the same payment status twice under optimistic locking', function () {
    $queue = CrmQueue::factory()->create();
    $agent = User::factory()->create(['team_id' => $queue->team_id]);
    $lead = Lead::factory()->create(['queue_id' => $queue->id, 'state' => LeadState::Queued, 'version' => 1]);
    $claimed = app(ClaimService::class)->claim($lead, $agent);
    $working = app(ClaimService::class)->startWorking($claimed, $agent);
    $order = app(OrderHandoffService::class)->qualify($working, $agent, [
        'sku' => 'starter-monthly',
        'name' => 'Starter Monthly',
        'amount_minor' => 4900,
    ]);

    $consumer = app(PaymentEventConsumer::class);
    $consumer->ingest('checkouthub', 'evt_a', 'payment.succeeded', $order->id);
    $consumer->ingest('checkouthub', 'evt_b', 'payment.succeeded', $order->id);

    expect($order->fresh()->version)->toBe(2)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Succeeded);
});
