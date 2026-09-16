<?php

namespace App\Services;

use App\Domain\Leads\LeadStateMachine;
use App\Enums\LeadState;
use App\Enums\OutboxStatus;
use App\Enums\PaymentStatus;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrderHandoffService
{
    public function __construct(private readonly LeadStateMachine $states)
    {
    }

    /**
     * @param  array{sku: string, name: string, amount_minor: int, currency?: string}  $product
     */
    public function qualify(Lead $lead, User $agent, array $product): Order
    {
        return DB::transaction(function () use ($lead, $agent, $product) {
            $qualified = $this->states->transition(
                $lead,
                LeadState::Qualified,
                'converted_to_order',
                $agent,
            );

            $order = Order::query()->create([
                'lead_id' => $qualified->id,
                'product_sku' => $product['sku'],
                'product_name' => $product['name'],
                'amount_minor' => $product['amount_minor'],
                'currency' => $product['currency'] ?? 'USD',
                'lead_snapshot' => [
                    'id' => $qualified->id,
                    'name' => $qualified->name,
                    'email' => $qualified->email,
                    'phone' => $qualified->phone,
                    'campaign_id' => $qualified->campaign_id,
                    'source_id' => $qualified->source_id,
                    'product_line' => $qualified->product_line,
                    'tags' => $qualified->tags,
                    'converted_at' => now()->toIso8601String(),
                ],
                'pricing_snapshot' => [
                    'sku' => $product['sku'],
                    'name' => $product['name'],
                    'amount_minor' => $product['amount_minor'],
                    'currency' => $product['currency'] ?? 'USD',
                ],
                'payment_status' => PaymentStatus::Pending,
                'version' => 1,
            ]);

            OutboxEvent::query()->create([
                'event_id' => (string) Str::uuid(),
                'aggregate_type' => 'order',
                'aggregate_id' => $order->id,
                'event_type' => 'order.created',
                'payload' => [
                    'order_id' => $order->id,
                    'lead_id' => $qualified->id,
                    'product' => $order->pricing_snapshot,
                    'customer' => $order->lead_snapshot,
                ],
                'status' => OutboxStatus::Pending,
            ]);

            return $order;
        });
    }

    public function disqualify(Lead $lead, User $agent, string $reasonCode): Lead
    {
        return $this->states->transition($lead, LeadState::Disqualified, $reasonCode, $agent);
    }

    public function markLost(Lead $lead, User $agent, string $reasonCode): Lead
    {
        return $this->states->transition($lead, LeadState::Lost, $reasonCode, $agent);
    }

    public function recycleLost(Lead $lead, string $reasonCode = 'reengagement'): Lead
    {
        return $this->states->transition(
            $lead,
            LeadState::Queued,
            $reasonCode,
            attributes: [
                'assigned_agent_id' => null,
                'claimed_at' => null,
                'sla_status' => 'ok',
            ],
        );
    }
}
