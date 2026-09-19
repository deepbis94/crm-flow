<?php

namespace App\Services;

use App\Support\Lua;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

final class LeadLock
{
    public function acquire(string $leadId, ?int $ttlMs = null): ?string
    {
        $token = (string) Str::uuid();
        $ttl = $ttlMs ?? (int) config('crmflow.claim_lock_ttl_ms', 300000);
        $ok = Redis::set($this->key($leadId), $token, 'PX', $ttl, 'NX');

        return $ok ? $token : null;
    }

    public function release(string $leadId, string $token): bool
    {
        return (int) Lua::eval('lock_release', [$this->key($leadId)], [$token]) === 1;
    }

    public function token(string $leadId): ?string
    {
        $value = Redis::get($this->key($leadId));

        return is_string($value) ? $value : null;
    }

    public function acquireAgent(int|string $agentId, ?int $ttlMs = null): ?string
    {
        $token = (string) Str::uuid();
        $ttl = $ttlMs ?? 5000;
        $ok = Redis::set($this->agentKey($agentId), $token, 'PX', $ttl, 'NX');

        return $ok ? $token : null;
    }

    public function waitForAgent(int|string $agentId, int $tries = 25, int $sleepMs = 20): ?string
    {
        for ($i = 0; $i < $tries; $i++) {
            $token = $this->acquireAgent($agentId);
            if ($token !== null) {
                return $token;
            }
            usleep($sleepMs * 1000);
        }

        return null;
    }

    public function releaseAgent(int|string $agentId, string $token): bool
    {
        return (int) Lua::eval('lock_release', [$this->agentKey($agentId)], [$token]) === 1;
    }

    public function key(string $leadId): string
    {
        return "lock:lead:{$leadId}";
    }

    public function agentKey(int|string $agentId): string
    {
        return "lock:agent:{$agentId}:claim";
    }
}
