<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Order::query()->latest()->limit(100)->get(),
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(['data' => $order->load('lead')]);
    }
}
