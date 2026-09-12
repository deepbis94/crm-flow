<?php

namespace Database\Seeders;

use App\Enums\AssignmentStrategyName;
use App\Models\CrmQueue;
use App\Models\Source;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public const DEMO_API_KEY = 'cf_live_demo_a1b2c3d4e5f6';

    public function run(): void
    {
        $team = Team::query()->updateOrCreate(
            ['slug' => 'growth'],
            ['name' => 'Growth']
        );

        User::query()->updateOrCreate(
            ['email' => 'agent@crmflow.test'],
            [
                'name' => 'Avery Agent',
                'password' => Hash::make('password'),
                'team_id' => $team->id,
                'role' => 'agent',
                'tags' => ['starter', 'pro'],
                'max_concurrency' => 5,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'agent2@crmflow.test'],
            [
                'name' => 'Blake Agent',
                'password' => Hash::make('password'),
                'team_id' => $team->id,
                'role' => 'agent',
                'tags' => ['enterprise'],
                'max_concurrency' => 5,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'supervisor@crmflow.test'],
            [
                'name' => 'Sam Supervisor',
                'password' => Hash::make('password'),
                'team_id' => $team->id,
                'role' => 'supervisor',
                'tags' => [],
                'max_concurrency' => 0,
            ]
        );

        $source = Source::query()->updateOrCreate(
            ['api_key_hash' => Source::hashApiKey(self::DEMO_API_KEY)],
            [
                'name' => 'Landing page — spring campaign',
                'form_id' => 'form-spring',
                'campaign_id' => 'camp-spring',
                'api_key_prefix' => substr(self::DEMO_API_KEY, 0, 12),
                'rate_limit_per_second' => 200,
                'rate_limit_burst' => 5000,
                'is_active' => true,
            ]
        );

        CrmQueue::query()->updateOrCreate(
            ['team_id' => $team->id, 'name' => 'Inbound starter'],
            [
                'source_id' => $source->id,
                'scope' => 'source',
                'product_line' => 'starter',
                'priority_weight' => 100,
                'max_concurrency_per_agent' => 3,
                'assignment_strategy' => AssignmentStrategyName::RoundRobin,
                'first_touch_sla_seconds' => 300,
                'followup_sla_seconds' => 86400,
                'idle_recycle_seconds' => 3600,
                'is_active' => true,
            ]
        );

        CrmQueue::query()->updateOrCreate(
            ['team_id' => $team->id, 'name' => 'Enterprise skill'],
            [
                'source_id' => $source->id,
                'scope' => 'product',
                'product_line' => 'enterprise',
                'priority_weight' => 200,
                'max_concurrency_per_agent' => 2,
                'assignment_strategy' => AssignmentStrategyName::SkillBased,
                'first_touch_sla_seconds' => 180,
                'followup_sla_seconds' => 43200,
                'idle_recycle_seconds' => 1800,
                'is_active' => true,
            ]
        );
    }
}
