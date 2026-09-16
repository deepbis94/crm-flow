<?php

use App\Http\Controllers\Api\V1\InternalWorkerController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\LeadIntakeController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\QueueController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', [InternalWorkerController::class, 'health']);

Route::post('/v1/leads', [LeadIntakeController::class, 'store'])->middleware('source.api');

Route::middleware('payment.webhook')->group(function () {
    Route::post('/v1/webhooks/payments', [PaymentWebhookController::class, 'store']);
});

Route::get('/v1/leads', [LeadController::class, 'index']);
Route::get('/v1/leads/{lead}', [LeadController::class, 'show']);
Route::post('/v1/leads/{lead}/claim', [LeadController::class, 'claim']);
Route::post('/v1/leads/{lead}/working', [LeadController::class, 'working']);
Route::post('/v1/leads/{lead}/notes', [LeadController::class, 'note']);
Route::post('/v1/leads/{lead}/convert', [LeadController::class, 'convert']);
Route::post('/v1/leads/{lead}/disqualify', [LeadController::class, 'disqualify']);
Route::get('/v1/queues', [QueueController::class, 'index']);
Route::get('/v1/agents', [QueueController::class, 'agents']);
Route::get('/v1/orders', [OrderController::class, 'index']);
Route::get('/v1/orders/{order}', [OrderController::class, 'show']);

Route::middleware('worker')->prefix('v1/internal')->group(function () {
    Route::post('/leads/process', [InternalWorkerController::class, 'processLead']);
    Route::post('/leads/auto-assign', [InternalWorkerController::class, 'autoAssign']);
    Route::post('/sla/sweep', [InternalWorkerController::class, 'sweepSla']);
    Route::post('/leads/recycle', [InternalWorkerController::class, 'recycleIdle']);
    Route::post('/leads/release-expired', [InternalWorkerController::class, 'releaseExpired']);
    Route::post('/outbox/relay', [InternalWorkerController::class, 'relayOutbox']);
    Route::post('/dead-letter/retry', [InternalWorkerController::class, 'retryDead']);
    Route::get('/dead-letter', [InternalWorkerController::class, 'deadLetters']);
});
