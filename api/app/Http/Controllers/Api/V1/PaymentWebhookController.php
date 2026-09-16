<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentEventConsumer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function store(Request $request, PaymentEventConsumer $consumer): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
            'event_id' => ['required', 'string'],
            'type' => ['required', 'string'],
            'order_id' => ['required', 'uuid'],
            'payload' => ['nullable', 'array'],
        ]);

        $result = $consumer->ingest(
            $data['provider'],
            $data['event_id'],
            $data['type'],
            $data['order_id'],
            $data['payload'] ?? [],
        );

        return response()->json([
            'status' => $result['duplicate'] ? 'duplicate' : 'accepted',
            'applied' => $result['applied'],
        ]);
    }
}
