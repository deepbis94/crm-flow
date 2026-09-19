<?php

namespace App\Services;

use App\Enums\OutboxStatus;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\Redis;

final class OutboxRelayService
{
    public function publishPending(int $limit = 50): int
    {
        $events = OutboxEvent::query()
            ->where(function ($query) {
                $query->where('status', OutboxStatus::Pending)
                    ->orWhere(function ($retry) {
                        $retry->where('status', OutboxStatus::Failed)
                            ->where('attempts', '<', 8);
                    });
            })
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $published = 0;
        foreach ($events as $event) {
            if ($this->publish($event)) {
                $published++;
            }
        }

        return $published;
    }

    public function publish(OutboxEvent $event): bool
    {
        $stream = (string) config('crmflow.streams.order_created');

        try {
            $this->xadd($stream, [
                'event_id' => $event->event_id,
                'event_type' => $event->event_type,
                'payload' => json_encode($event->payload, JSON_THROW_ON_ERROR),
            ]);

            $event->forceFill([
                'status' => OutboxStatus::Published,
                'published_at' => now(),
                'next_attempt_at' => null,
                'last_error' => null,
            ])->save();

            return true;
        } catch (\Throwable $e) {
            $attempts = $event->attempts + 1;
            $dead = $attempts >= 8;
            $event->forceFill([
                'attempts' => $attempts,
                'status' => $dead ? OutboxStatus::Dead : OutboxStatus::Failed,
                'next_attempt_at' => $dead ? null : now()->addSeconds(2 ** $attempts),
                'last_error' => $e->getMessage(),
            ])->save();

            if ($dead) {
                Redis::rpush('crmflow:jobs:dead', json_encode([
                    'event_id' => $event->event_id,
                    'error' => $e->getMessage(),
                ]));
            }

            return false;
        }
    }

    public function replay(?int $fromId = null, ?int $toId = null): int
    {
        $query = OutboxEvent::query()->orderBy('id');
        if ($fromId) {
            $query->where('id', '>=', $fromId);
        }
        if ($toId) {
            $query->where('id', '<=', $toId);
        }

        $count = 0;
        foreach ($query->get() as $event) {
            $event->forceFill([
                'status' => OutboxStatus::Pending,
                'attempts' => 0,
                'next_attempt_at' => null,
            ])->save();
            if ($this->publish($event->fresh())) {
                $count++;
            }
        }

        return $count;
    }

    public function retryDead(?string $eventId = null): int
    {
        $query = OutboxEvent::query()->where('status', OutboxStatus::Dead);
        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $count = 0;
        foreach ($query->get() as $event) {
            $event->forceFill([
                'status' => OutboxStatus::Pending,
                'attempts' => 0,
                'next_attempt_at' => null,
                'last_error' => null,
            ])->save();
            if ($this->publish($event->fresh())) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Predis and PhpRedis disagree on XADD argument order.
     * PhpRedis/Laravel: xadd(key, id, fields)
     * Predis:           xadd(key, fields, id)
     *
     * @param  array<string, string>  $fields
     */
    private function xadd(string $stream, array $fields): mixed
    {
        if (config('database.redis.client') === 'predis') {
            return Redis::xadd($stream, $fields, '*');
        }

        return Redis::xadd($stream, '*', $fields);
    }
}
