<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmQueue;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class QueueController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CrmQueue::query()->with('team')->where('is_active', true)->get(),
        ]);
    }

    public function agents(): JsonResponse
    {
        return response()->json([
            'data' => User::query()->select('id', 'name', 'email', 'role', 'team_id', 'tags', 'max_concurrency')->get(),
        ]);
    }
}
