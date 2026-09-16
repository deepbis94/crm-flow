<?php

namespace App\Services;

use App\Exceptions\RateLimitedException;
use App\Models\Source;
use App\Support\WorkerJobs;
use Illuminate\Support\Str;

final class LeadIntakeService
{
    public function __construct(private readonly TokenBucket $buckets)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{intake_id: string, status: string}
     */
    public function accept(Source $source, array $payload): array
    {
        $sourceBucket = 'source:'.($payload['form_id'] ?? $source->form_id ?? $source->id);
        $keyBucket = 'apikey:'.$source->id;

        $sourceOk = $this->buckets->allow(
            $sourceBucket,
            (float) $source->rate_limit_per_second,
            (int) $source->rate_limit_burst,
        );
        $keyOk = $this->buckets->allow(
            $keyBucket,
            (float) config('crmflow.intake.key_refill_per_second'),
            (int) config('crmflow.intake.key_burst'),
        );

        if (! $sourceOk || ! $keyOk) {
            throw new RateLimitedException(1);
        }

        $intakeId = (string) Str::uuid();

        WorkerJobs::push('leads.intake', [
            'intake_id' => $intakeId,
            'source_id' => $source->id,
            'name' => $payload['name'],
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'campaign_id' => $payload['campaign_id'] ?? $source->campaign_id,
            'form_id' => $payload['form_id'] ?? $source->form_id,
            'product_line' => $payload['product_line'] ?? null,
            'tags' => $payload['tags'] ?? [],
        ], $intakeId);

        return [
            'intake_id' => $intakeId,
            'status' => 'accepted',
        ];
    }
}
