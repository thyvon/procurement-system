<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates inbound webhooks from the Telegram Bot API (setWebhook).
 *
 * Telegram echoes the setWebhook secret_token back verbatim in the
 * X-Telegram-Bot-Api-Secret-Token header — compared with hash_equals here.
 * Fails closed when no secret is configured (503), mirroring
 * VerifyTelegramWebhook for the TDLib service.
 */
class VerifyTelegramBotWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.telegram.bot_webhook_secret');

        if (blank($secret)) {
            abort(503, 'Telegram bot webhook is not configured.');
        }

        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! is_string($provided) || $provided === '') {
            abort(401, 'Missing bot webhook secret.');
        }

        if (! hash_equals($secret, $provided)) {
            abort(401, 'Invalid bot webhook secret.');
        }

        return $next($request);
    }
}
