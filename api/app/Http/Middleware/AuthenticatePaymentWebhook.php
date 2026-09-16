<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePaymentWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->header('X-Webhook-Secret');
        $expected = (string) config('crmflow.payment_webhook_secret');

        if (! is_string($secret) || $expected === '' || ! hash_equals($expected, $secret)) {
            return response()->json(['message' => 'Invalid payment webhook secret.'], 401);
        }

        return $next($request);
    }
}
