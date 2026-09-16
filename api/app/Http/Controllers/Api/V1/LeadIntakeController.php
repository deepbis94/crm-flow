<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Services\LeadIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadIntakeController extends Controller
{
    public function store(Request $request, LeadIntakeService $intake): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'campaign_id' => ['nullable', 'string', 'max:64'],
            'form_id' => ['nullable', 'string', 'max:64'],
            'product_line' => ['nullable', 'string', 'max:64'],
            'tags' => ['nullable', 'array'],
        ]);

        /** @var Source $source */
        $source = $request->attributes->get('source');
        $accepted = $intake->accept($source, $data);

        return response()->json($accepted, 202);
    }
}
