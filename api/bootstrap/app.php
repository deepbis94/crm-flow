<?php

use App\Exceptions\ConcurrentLeadTransitionException;
use App\Exceptions\IllegalLeadTransitionException;
use App\Exceptions\LeadAlreadyClaimedException;
use App\Exceptions\RateLimitedException;
use App\Http\Middleware\AuthenticatePaymentWebhook;
use App\Http\Middleware\AuthenticateSourceApiKey;
use App\Http\Middleware\AuthenticateWorker;
use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(CorrelationId::class);
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'source.api' => AuthenticateSourceApiKey::class,
            'worker' => AuthenticateWorker::class,
            'payment.webhook' => AuthenticatePaymentWebhook::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([
            RateLimitedException::class,
            IllegalLeadTransitionException::class,
            ConcurrentLeadTransitionException::class,
            LeadAlreadyClaimedException::class,
        ]);
        $exceptions->render(function (RateLimitedException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 429)
                ->header('Retry-After', (string) $e->retryAfterSeconds);
        });
        $exceptions->render(fn (IllegalLeadTransitionException $e) => response()->json(['message' => $e->getMessage()], 409));
        $exceptions->render(fn (ConcurrentLeadTransitionException $e) => response()->json(['message' => $e->getMessage()], 409));
        $exceptions->render(fn (LeadAlreadyClaimedException $e) => response()->json(['message' => $e->getMessage()], 409));
    })->create();
