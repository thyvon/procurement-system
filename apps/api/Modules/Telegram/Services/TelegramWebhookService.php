<?php

namespace Modules\Telegram\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Bot API webhook lifecycle: reports what Telegram currently has registered
 * (getWebhookInfo) and points it at this deployment (setWebhook). The
 * webhook URL is built from the whitelisted `webhook_base_url` system
 * setting; registration passes the write-only bot webhook secret as the
 * setWebhook secret_token so VerifyTelegramBotWebhook can authenticate the
 * inbound updates.
 *
 * Fails closed like VerifyTelegramBotWebhook: no token, secret, or origin
 * means 503 rather than half-configuring Telegram. The bot token rides in
 * the request URL, so failures surface as static messages only — no path
 * can persist or log it.
 */
class TelegramWebhookService
{
    /** Route (under the public origin) VerifyTelegramBotWebhook guards. */
    public const WEBHOOK_PATH = '/api/internal/telegram/bot';

    /**
     * Current registration as Telegram sees it, plus the URL this
     * deployment expects — the pair the UI needs to spot drift (a stale
     * origin after a domain change, for example).
     *
     * @return array{registered: bool, url: string, expectedUrl: string|null, pendingUpdateCount: int, lastErrorAt: string|null, lastErrorMessage: string|null}
     */
    public function status(): array
    {
        $this->requireConfig('services.telegram.bot_token', 'Telegram bot is not configured.');

        $result = $this->call('getWebhookInfo');
        $url = (string) ($result['url'] ?? '');
        $lastErrorDate = (int) ($result['last_error_date'] ?? 0);
        $origin = config('services.telegram.webhook_base_url');

        return [
            'registered' => $url !== '',
            'url' => $url,
            'expectedUrl' => is_string($origin) && $origin !== ''
                ? rtrim($origin, '/').self::WEBHOOK_PATH
                : null,
            'pendingUpdateCount' => (int) ($result['pending_update_count'] ?? 0),
            'lastErrorAt' => $lastErrorDate > 0
                ? Carbon::createFromTimestamp($lastErrorDate)->toIso8601String()
                : null,
            'lastErrorMessage' => isset($result['last_error_message'])
                ? (string) $result['last_error_message']
                : null,
        ];
    }

    /**
     * Point Telegram at this deployment's bot webhook route, then report
     * the fresh registration.
     *
     * @return array{registered: bool, url: string, expectedUrl: string|null, pendingUpdateCount: int, lastErrorAt: string|null, lastErrorMessage: string|null}
     */
    public function register(): array
    {
        $secret = $this->requireConfig(
            'services.telegram.bot_webhook_secret',
            'Telegram bot webhook secret is not configured.',
        );
        $origin = $this->requireConfig(
            'services.telegram.webhook_base_url',
            'Webhook base URL is not configured.',
        );

        $this->call('setWebhook', [
            'url' => rtrim($origin, '/').self::WEBHOOK_PATH,
            'secret_token' => $secret,
            // The bot handles message updates (/start deep links, chat
            // replies) and the inline-button taps on approval alerts;
            // anything else would bounce with a 422 and trigger Telegram's
            // retry loop.
            'allowed_updates' => json_encode(['message', 'callback_query'], JSON_THROW_ON_ERROR),
        ]);

        return $this->status();
    }

    /**
     * One Bot API method call. Returns `result` on `ok: true`; anything
     * else (HTTP failure or `ok: false`) aborts with the envelope message —
     * Telegram's own `description` when it provides one.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload = []): array
    {
        $token = $this->requireConfig('services.telegram.bot_token', 'Telegram bot is not configured.');

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->connectTimeout(5)
                ->post('https://api.telegram.org/bot'.$token.'/'.$method, $payload);
        } catch (ConnectionException) {
            abort(502, 'Telegram API is unreachable.');
        }

        return $this->unwrap($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function unwrap(Response $response): array
    {
        $body = $response->json();

        if ($response->failed() || ($body['ok'] ?? false) !== true) {
            $description = is_string($body['description'] ?? null)
                ? $body['description']
                : 'Telegram API request failed.';

            abort(502, $description);
        }

        $result = $body['result'] ?? [];

        return is_array($result) ? $result : [];
    }

    private function requireConfig(string $key, string $message): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            abort(503, $message);
        }

        return $value;
    }
}
