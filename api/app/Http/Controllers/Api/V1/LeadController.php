<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\NoteService;
use App\Services\OrderHandoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request, NoteService $notes): JsonResponse
    {
        $leads = $notes->search((string) $request->query('q', ''), $request->only([
            'queue_id', 'state', 'source_id', 'assigned_agent_id', 'sla_status',
        ]))->paginate(50);

        return response()->json($leads);
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead->load(['transitions.agent', 'notes.agent', 'assignedAgent', 'queue', 'source', 'order']);

        return response()->json(['data' => $lead]);
    }

    public function claim(Request $request, Lead $lead, ClaimService $claims): JsonResponse
    {
        $agent = $this->agent($request);
        $claimed = $claims->claim($lead, $agent);

        return response()->json(['data' => $claimed]);
    }

    public function working(Request $request, Lead $lead, ClaimService $claims): JsonResponse
    {
        $working = $claims->startWorking($lead, $this->agent($request));

        return response()->json(['data' => $working]);
    }

    public function note(Request $request, Lead $lead, NoteService $notes): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
            'type' => ['nullable', 'in:note,call_outcome,tag'],
        ]);

        $note = $notes->append($lead, $this->agent($request), $data['body'], $data['type'] ?? 'note');

        return response()->json(['data' => $note], 201);
    }

    public function convert(Request $request, Lead $lead, OrderHandoffService $handoff): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string'],
            'name' => ['required', 'string'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $order = $handoff->qualify($lead, $this->agent($request), $data);

        return response()->json(['data' => $order], 201);
    }

    public function disqualify(Request $request, Lead $lead, OrderHandoffService $handoff): JsonResponse
    {
        $data = $request->validate(['reason_code' => ['required', 'string']]);
        $updated = $handoff->disqualify($lead, $this->agent($request), $data['reason_code']);

        return response()->json(['data' => $updated]);
    }

    private function agent(Request $request): User
    {
        if ($request->user()) {
            return $request->user();
        }

        $id = $request->integer('agent_id');
        abort_unless($id, 401, 'Agent required.');

        return User::query()->findOrFail($id);
    }
}
