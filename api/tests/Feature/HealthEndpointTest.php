<?php

use App\Services\HealthService;

beforeEach(fn () => flushTestRedis());

it('reports queue depths and sla breach counts', function () {
    $snapshot = app(HealthService::class)->snapshot();

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    expect($snapshot)->toHaveKeys(['queues', 'worker_lag', 'sla_breaches']);
});
