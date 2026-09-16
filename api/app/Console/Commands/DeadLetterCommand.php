<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use App\Services\OutboxRelayService;
use Illuminate\Console\Command;

class DeadLetterCommand extends Command
{
    protected $signature = 'crmflow:dead-letter {action=list : list|retry|inspect} {--event=}';

    protected $description = 'Inspect or retry dead-lettered outbox events.';

    public function handle(OutboxRelayService $relay): int
    {
        $action = $this->argument('action');

        if ($action === 'retry') {
            $count = $relay->retryDead($this->option('event'));
            $this->info("Retried {$count} dead-letter events.");

            return self::SUCCESS;
        }

        $rows = OutboxEvent::query()
            ->where('status', 'dead')
            ->when($this->option('event'), fn ($q, $id) => $q->where('event_id', $id))
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'event_id', 'event_type', 'attempts', 'last_error', 'updated_at']);

        if ($rows->isEmpty()) {
            $this->info('Dead-letter queue is empty.');

            return self::SUCCESS;
        }

        $this->table(['id', 'event_id', 'type', 'attempts', 'error', 'updated'], $rows->map(fn ($row) => [
            $row->id,
            $row->event_id,
            $row->event_type,
            $row->attempts,
            $row->last_error,
            $row->updated_at,
        ])->all());

        return self::SUCCESS;
    }
}
