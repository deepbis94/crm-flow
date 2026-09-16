<?php

namespace App\Console\Commands;

use App\Services\OutboxRelayService;
use Illuminate\Console\Command;

class OutboxReplayCommand extends Command
{
    protected $signature = 'crmflow:outbox-replay {--from=} {--to=}';

    protected $description = 'Re-publish outbox events in an id range (consumers dedupe on event_id).';

    public function handle(OutboxRelayService $relay): int
    {
        $from = $this->option('from') ? (int) $this->option('from') : null;
        $to = $this->option('to') ? (int) $this->option('to') : null;
        $count = $relay->replay($from, $to);
        $this->info("Replayed {$count} outbox events.");

        return self::SUCCESS;
    }
}
