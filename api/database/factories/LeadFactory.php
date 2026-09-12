<?php

namespace Database\Factories;

use App\Enums\LeadState;
use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'intake_id' => (string) Str::uuid(),
            'source_id' => Source::factory(),
            'queue_id' => CrmQueue::factory(),
            'state' => LeadState::Queued,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('555-###-####'),
            'campaign_id' => 'camp-demo',
            'form_id' => 'form-demo',
            'product_line' => 'starter',
            'tags' => ['starter'],
            'priority' => 100,
            'sla_status' => 'ok',
            'version' => 1,
        ];
    }

    public function newState(): static
    {
        return $this->state(fn () => ['state' => LeadState::New]);
    }

    public function queued(): static
    {
        return $this->state(fn () => ['state' => LeadState::Queued]);
    }

    public function claimed(): static
    {
        return $this->state(fn () => ['state' => LeadState::Claimed, 'claimed_at' => now()]);
    }

    public function working(): static
    {
        return $this->state(fn () => ['state' => LeadState::Working]);
    }
}
