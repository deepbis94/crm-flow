<?php

namespace App\Services;

use App\Enums\LeadState;
use App\Enums\OutboxStatus;
use App\Models\Lead;
use App\Models\OutboxEvent;
use App\Models\SlaBreach;
use App\Support\WorkerJobs;
use Illuminate\Support\Facades\Redis;

final class HealthService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $stream = (string) config('crmflow.streams.order_created');
        $length = 0;
        try {
            $length = (int) Redis::xlen($stream);
        } catch (\Throwable) {
            $length = 0;
        }

        return [
            'status' => 'ok',
            'queues' => [
                'leads.intake' => WorkerJobs::depth('leads.intake'),
                'notifications.sla' => WorkerJobs::depth('notifications.sla'),
                'dead' => WorkerJobs::depth('dead'),
            ],
            'worker_lag' => [
                'intake_depth' => WorkerJobs::depth('leads.intake'),
                'outbox_pending' => OutboxEvent::query()->where('status', OutboxStatus::Pending)->count(),
                'outbox_dead' => OutboxEvent::query()->where('status', OutboxStatus::Dead)->count(),
                'stream_length' => $length,
            ],
            'leads' => [
                'queued' => Lead::query()->where('state', LeadState::Queued)->count(),
                'claimed' => Lead::query()->where('state', LeadState::Claimed)->count(),
                'working' => Lead::query()->where('state', LeadState::Working)->count(),
            ],
            'sla_breaches' => SlaBreach::query()->count(),
        ];
    }
}
