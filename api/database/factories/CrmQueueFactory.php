<?php

namespace Database\Factories;

use App\Enums\AssignmentStrategyName;
use App\Models\CrmQueue;
use App\Models\Source;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrmQueue>
 */
class CrmQueueFactory extends Factory
{
    protected $model = CrmQueue::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'source_id' => Source::factory(),
            'name' => fake()->words(2, true).' queue',
            'scope' => 'source',
            'product_line' => 'starter',
            'priority_weight' => 100,
            'max_concurrency_per_agent' => 3,
            'assignment_strategy' => AssignmentStrategyName::RoundRobin,
            'first_touch_sla_seconds' => 300,
            'followup_sla_seconds' => 86400,
            'idle_recycle_seconds' => 3600,
            'is_active' => true,
        ];
    }
}
