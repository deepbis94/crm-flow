<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

final class PaymentEventConsumer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{duplicate: bool, applied: bool}
     */
    public function ingest(string $provider, string $eventId, string $type, string $orderId, array $payload = []): array
    {
        try {
            PaymentEvent::query()->create([
                'provider' => $provider,
                'event_id' => $eventId,
                'order_id' => $orderId,
                'type' => $type,
                'payload' => $payload,
                'processed_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return ['duplicate' => true, 'applied' => false];
            }
            throw $e;
        }

        $applied = $this->apply($orderId, $type);

        Log::info('payment_event_consumed', [
            'provider' => $provider,
            'event_id' => $eventId,
            'type' => $type,
            'order_id' => $orderId,
            'applied' => $applied,
        ]);

        return ['duplicate' => false, 'applied' => $applied];
    }

    private function apply(string $orderId, string $type): bool
    {
        $order = Order::query()->find($orderId);
        if (! $order) {
            return false;
        }

        $target = match ($type) {
            'payment.succeeded' => PaymentStatus::Succeeded,
            'payment.failed' => PaymentStatus::Failed,
            'refund', 'payment.refunded' => PaymentStatus::Refunded,
            default => null,
        };

        if ($target === null || $order->payment_status === $target) {
            return false;
        }

        $updated = Order::query()
            ->where('id', $order->id)
            ->where('version', $order->version)
            ->where('payment_status', $order->payment_status->value)
            ->update([
                'payment_status' => $target->value,
                'version' => $order->version + 1,
            ]);

        return $updated === 1;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';
        $message = $e->getMessage();

        return $sqlState === '23000'
            || str_contains($message, 'UNIQUE')
            || str_contains($message, 'unique')
            || str_contains($message, 'Integrity constraint');
    }
}
