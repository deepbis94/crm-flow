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

    public function key(string $leadId): string
    {
        return "lock:lead:{$leadId}";
    }
}
