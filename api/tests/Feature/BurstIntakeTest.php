<?php

use App\Models\Source;
use Database\Seeders\DatabaseSeeder;

beforeEach(fn () => flushTestRedis());

it('returns 202 immediately and 429 with Retry-After when the bucket is empty', function () {
    $source = Source::factory()->withPlainKey('cf_test_burst')->create([
        'rate_limit_per_second' => 1,
        'rate_limit_burst' => 3,
    ]);

    $ok = 0;
    $limited = 0;
    for ($i = 0; $i < 6; $i++) {
        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Burst '.$i,
            'email' => "burst{$i}@example.com",
        ], ['X-Api-Key' => 'cf_test_burst']);

        if ($response->status() === 202) {
            $ok++;
        }
        if ($response->status() === 429) {
            $limited++;
            $response->assertHeader('Retry-After');
        }
    }

    expect($ok)->toBe(3)->and($limited)->toBe(3);
});

it('accepts a 5000-request campaign burst without dropping silently', function () {
    Source::factory()->withPlainKey(DatabaseSeeder::DEMO_API_KEY)->create([
        'form_id' => 'form-5000',
        'rate_limit_per_second' => 1000,
        'rate_limit_burst' => 5000,
    ]);

    config([
        'crmflow.intake.key_refill_per_second' => 1000,
        'crmflow.intake.key_burst' => 5000,
    ]);

    $accepted = 0;
    $limited = 0;
    $other = 0;

    for ($i = 0; $i < 5000; $i++) {
        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Campaign '.$i,
            'email' => "c{$i}@example.com",
            'form_id' => 'form-5000',
        ], ['X-Api-Key' => DatabaseSeeder::DEMO_API_KEY]);

        match ($response->status()) {
            202 => $accepted++,
            429 => $limited++,
            default => $other++,
        };
    }

    expect($other)->toBe(0)
        ->and($accepted + $limited)->toBe(5000)
        ->and($accepted)->toBe(5000);
});
