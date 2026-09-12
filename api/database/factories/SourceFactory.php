<?php

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Source>
 */
class SourceFactory extends Factory
{
    public function definition(): array
    {
        $key = 'cf_test_'.fake()->unique()->regexify('[a-z0-9]{12}');

        return [
            'name' => fake()->company().' campaign',
            'form_id' => 'form-'.fake()->numerify('###'),
            'campaign_id' => 'camp-'.fake()->numerify('###'),
            'api_key_prefix' => substr($key, 0, 12),
            'api_key_hash' => Source::hashApiKey($key),
            'rate_limit_per_second' => 200,
            'rate_limit_burst' => 5000,
            'is_active' => true,
        ];
    }

    public function withPlainKey(string $plain): static
    {
        return $this->state(fn () => [
            'api_key_prefix' => substr($plain, 0, 12),
            'api_key_hash' => Source::hashApiKey($plain),
        ]);
    }
}
