<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\LeadState;
use App\Http\Controllers\Controller;
use App\Models\CrmQueue;
use App\Models\Lead;
use App\Models\OutboxEvent;
use App\Models\SlaBreach;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\NoteService;
use App\Services\OrderHandoffService;
use App\Services\OutboxRelayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentWorkspaceController extends Controller
{
    public function queue(Request $request): Response
    {
        $user = $request->user();
        $mine = Lead::query()
            ->with(['queue', 'source'])
            ->where('assigned_agent_id', $user->id)
            ->whereIn('state', [LeadState::Claimed, LeadState::Working])
            ->orderByDesc('priority')
            ->get();

        $available = Lead::query()
            ->with(['queue', 'source'])
            ->where('state', LeadState::Queued)
            ->when($user->team_id, fn ($q) => $q->whereHas('queue', fn ($qq) => $qq->where('team_id', $user->team_id)))
            ->orderByDesc('priority')
            ->limit(50)
            ->get();

        return Inertia::render('Agent/Queue', [
            'mine' => $mine,
            'available' => $available,
        ]);
    }

    public function show(Lead $lead): Response
    {
        $lead->load(['transitions.agent', 'notes.agent', 'assignedAgent', 'queue', 'source', 'order']);

        return Inertia::render('Agent/Lead', ['lead' => $lead]);
    }

    public function claim(Request $request, Lead $lead, ClaimService $claims): RedirectResponse
    {
        $claims->claim($lead, $request->user());

        return redirect()->route('workspace.leads.show', $lead);
    }

    public function working(Request $request, Lead $lead, ClaimService $claims): RedirectResponse
    {
        $claims->startWorking($lead, $request->user());

        return back();
    }

    public function note(Request $request, Lead $lead, NoteService $notes): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
            'type' => ['nullable', 'in:note,call_outcome,tag'],
        ]);
        $notes->append($lead, $request->user(), $data['body'], $data['type'] ?? 'note');

        return back();
    }

    public function convert(Request $request, Lead $lead, OrderHandoffService $handoff): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string'],
            'name' => ['required', 'string'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);
        $handoff->qualify($lead, $request->user(), $data);

        return back()->with('status', 'Order created and handed off.');
    }

    public function supervisor(): Response
    {
        $queues = CrmQueue::query()->with('team')->get();
        $heatmap = [];
        foreach ($queues as $queue) {
            $row = ['queue' => $queue->name, 'id' => $queue->id];
            foreach (LeadState::cases() as $state) {
                $row[$state->value] = Lead::query()->where('queue_id', $queue->id)->where('state', $state)->count();
            }
            $heatmap[] = $row;
        }

        $agents = User::query()->where('role', 'agent')->get()->map(function (User $agent) {
            $open = Lead::query()
                ->where('assigned_agent_id', $agent->id)
                ->whereIn('state', [LeadState::Claimed, LeadState::Working])
                ->count();

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'open' => $open,
                'cap' => $agent->max_concurrency,
                'utilization' => $agent->max_concurrency > 0 ? round($open / $agent->max_concurrency, 2) : 0,
            ];
        });

        return Inertia::render('Supervisor/Dashboard', [
            'heatmap' => $heatmap,
            'breaches' => SlaBreach::query()->with('lead')->latest('escalated_at')->limit(50)->get(),
            'agents' => $agents,
            'deadLetters' => OutboxEvent::query()->where('status', 'dead')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function retryDead(Request $request, OutboxRelayService $relay): RedirectResponse
    {
        $relay->retryDead($request->string('event_id')->toString() ?: null);

        return back();
    }
}
