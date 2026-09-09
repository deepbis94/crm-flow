<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('crmflow:release-expired', function () {
    $n = app(\App\Services\ClaimService::class)->releaseExpiredLocks();
    $this->info("Released {$n} leads whose claim locks expired.");
})->purpose('Return leads to queue after Redis claim locks expire');
