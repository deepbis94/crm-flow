<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\OutboxEvent;
use App\Services\ClaimService;
use App\Services\HealthService;
use App\Services\IdleRecycleService;
use App\Services\LeadProcessor;
use App\Services\OutboxRelayService;
use App\Services\SlaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalWorkerController extends Controller
{
    public function processLead(Request $request, LeadProcessor $processor): JsonResponse
    {
        $payload = $request->validate([
            'intake_id' => ['required', 'string'],
            'source_id' => ['required', 'integer'],
            'name' => ['required', 'string'],
            'email' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'campaign_id' => ['nullable', 'string'],
            'form_id' => ['nullable', 'string'],
            'product_line' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
        ]);

        $lead = $processor->process($payload);

        return response()->json(['data' => $lead]);
    }

    public function sweepSla(SlaService $sla): JsonResponse
    {
        return response()->json(['breached' => $sla->sweep()]);
    }

    public function recycleIdle(IdleRecycleService $recycle): JsonResponse
    {
        return response()->json(['recycled' => $recycle->recycle()]);
    }

    public function releaseExpired(ClaimService $claims): JsonResponse
    {
        return response()->json(['released' => $claims->releaseExpiredLocks()]);
    }

    public function relayOutbox(OutboxRelayService $relay): JsonResponse
    {
        return response()->json(['published' => $relay->publishPending()]);
    }

    public function retryDead(Request $request, OutboxRelayService $relay): JsonResponse
    {
        $eventId = $request->string('event_id')->toString() ?: null;

        return response()->json(['retried' => $relay->retryDead($eventId)]);
    }

    public function health(HealthService $health): JsonResponse
    {
        return response()->json($health->snapshot());
    }

    public function autoAssign(Request $request, ClaimService $claims): JsonResponse
    {
        $lead = Lead::query()->findOrFail($request->string('lead_id'));

        return response()->json(['data' => $claims->autoAssign($lead)]);
    }

    public function deadLetters(): JsonResponse
    {
        return response()->json([
            'data' => OutboxEvent::query()->where('status', 'dead')->orderByDesc('id')->limit(100)->get(),
        ]);
    }
}
