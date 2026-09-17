<?php

use App\Domain\Leads\LeadStateMachine;
use App\Enums\LeadState;
use App\Exceptions\IllegalLeadTransitionException;
use App\Models\Lead;
use App\Models\LeadTransition;

it('persists the full lifecycle for reconstructable history', function () {
    $agent = \App\Models\User::factory()->create();
    $lead = Lead::factory()->create(['state' => LeadState::New, 'version' => 1]);
    $sm = app(LeadStateMachine::class);

    $lead = $sm->transition($lead, LeadState::Queued, 'intake_enqueued');
    $lead = $sm->transition($lead, LeadState::Claimed, 'agent_claim', $agent, ['assigned_agent_id' => $agent->id]);
    $lead = $sm->transition($lead, LeadState::Working, 'agent_working', $agent);
    $lead = $sm->transition($lead, LeadState::Lost, 'no_answer', $agent);
    $lead = $sm->transition($lead, LeadState::Queued, 'reengagement');

    expect($lead->state)->toBe(LeadState::Queued)
        ->and(LeadTransition::query()->where('lead_id', $lead->id)->count())->toBe(5)
        ->and(LeadTransition::query()->where('lead_id', $lead->id)->pluck('to_state')->map->value->all())
        ->toBe(['queued', 'claimed', 'working', 'lost', 'queued']);
});

it('rejects illegal transitions without crashing', function () {
    $lead = Lead::factory()->create(['state' => LeadState::New, 'version' => 1]);

    expect(fn () => app(LeadStateMachine::class)->transition($lead, LeadState::Qualified, 'bad'))
        ->toThrow(IllegalLeadTransitionException::class)
        ->and(\App\Models\RejectedTransition::query()->count())->toBe(1);
});
