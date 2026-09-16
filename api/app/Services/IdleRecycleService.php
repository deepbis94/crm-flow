<?php

namespace App\Services;

use App\Enums\LeadState;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;

final class IdleRecycleService
{
    public function __construct(private readonly ClaimService $claims)
    {
    }

    public function recycle(): int
    {
        $count = 0;
        $leads = Lead::query()
            ->whereIn('state', [LeadState::Claimed->value, LeadState::Working->value])
            ->with('queue')
            ->get();

        foreach ($leads as $lead) {
            $threshold = $lead->queue?->idle_recycle_seconds ?? 3600;
            $touched = $lead->last_touched_at ?? $lead->claimed_at ?? $lead->created_at;
            if ($touched->gt(now()->subSeconds($threshold))) {
                continue;
            }

            $this->claims->releaseToQueue($lead, 'idle_recycle');
            $count++;
            Log::info('idle_lead_recycled', ['lead_id' => $lead->id, 'threshold' => $threshold]);
        }

        return $count;
    }
}
