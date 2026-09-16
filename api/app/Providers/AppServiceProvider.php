<?php

namespace App\Providers;

use App\Contracts\AssignmentStrategy;
use App\Domain\Leads\LeadStateMachine;
use App\Domain\Queues\AssignmentStrategyFactory;
use App\Domain\Queues\LoadBalancedStrategy;
use App\Domain\Queues\RoundRobinStrategy;
use App\Domain\Queues\SkillBasedStrategy;
use App\Services\ClaimService;
use App\Services\HealthService;
use App\Services\IdleRecycleService;
use App\Services\LeadIntakeService;
use App\Services\LeadLock;
use App\Services\LeadProcessor;
use App\Services\NoteService;
use App\Services\OrderHandoffService;
use App\Services\OutboxRelayService;
use App\Services\PaymentEventConsumer;
use App\Services\SlaService;
use App\Services\TokenBucket;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TokenBucket::class);
        $this->app->singleton(LeadLock::class);
        $this->app->singleton(LeadStateMachine::class);
        $this->app->singleton(RoundRobinStrategy::class);
        $this->app->singleton(LoadBalancedStrategy::class);
        $this->app->singleton(SkillBasedStrategy::class);
        $this->app->singleton(AssignmentStrategyFactory::class, function ($app) {
            return new AssignmentStrategyFactory([
                'round_robin' => $app->make(RoundRobinStrategy::class),
                'load_balanced' => $app->make(LoadBalancedStrategy::class),
                'skill_based' => $app->make(SkillBasedStrategy::class),
            ]);
        });
        $this->app->singleton(LeadIntakeService::class);
        $this->app->singleton(LeadProcessor::class);
        $this->app->singleton(ClaimService::class);
        $this->app->singleton(SlaService::class);
        $this->app->singleton(IdleRecycleService::class);
        $this->app->singleton(OrderHandoffService::class);
        $this->app->singleton(OutboxRelayService::class);
        $this->app->singleton(PaymentEventConsumer::class);
        $this->app->singleton(NoteService::class);
        $this->app->singleton(HealthService::class);
        $this->app->tag([
            RoundRobinStrategy::class,
            LoadBalancedStrategy::class,
            SkillBasedStrategy::class,
        ], AssignmentStrategy::class);
    }

    public function boot(): void
    {
        //
    }
}
