<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates inbound webhooks from the TDLib service.
 *
 * Signature: hex-encoded HMAC-SHA256 over "{timestamp}.{rawBody}" in the
 * X-Telegram-Signature header; X-Telegram-Timestamp is unix seconds. Requests
 * older than 5 minutes are rejected (replay window) — the message-id unique
 * index backstops anything that slips through. Fails closed when no secret is
 * configured.
 */
class VerifyTelegramWebhook
{
    public const MAX_AGE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.telegram.webhook_secret');

        if (blank($secret)) {
            abort(503, 'Telegram webhook is not configured.');
        }

        $timestamp = $request->header('X-Telegram-Timestamp');
        $signature = $request->header('X-Telegram-Signature');

        if (! is_string($timestamp) || ! is_string($signature) || $signature === '') {
            abort(401, 'Missing webhook signature.');
        }

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            abort(401, 'Webhook timestamp expired.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
