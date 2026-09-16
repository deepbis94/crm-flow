<?php

namespace App\Support;

use App\Support\Correlation;
use Illuminate\Support\Facades\Redis;

final class WorkerJobs
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function push(string $queue, array $payload, string $idempotencyKey): void
    {
        Redis::rpush('crmflow:jobs:'.$queue, json_encode([
            'idempotencyKey' => $idempotencyKey,
            'payload' => $payload,
            'correlationId' => Correlation::id(),
        ], JSON_THROW_ON_ERROR));
    }

    public static function depth(string $queue): int
    {
        return (int) Redis::llen('crmflow:jobs:'.$queue);
    }
}
