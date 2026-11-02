<?php

namespace Modules\Telegram\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Modules\Telegram\Http\Requests\BotWebhookRequest;
use Modules\Telegram\Jobs\ProcessTelegramActionReply;
use Modules\Telegram\Jobs\ProcessTelegramCallback;
use Modules\Telegram\Jobs\ProcessTelegramLinkStart;

/**
 * Webhook target for the Bot API (/start deep-links, approval-alert button
 * taps, and the reason replies to reject/return prompts). VerifyTelegramBotWebhook
 * has already authenticated the secret_token — here we route the payload to
 * the matching queue job and answer 200 fast; parsing and acting happen in
 * the job. Routing only peeks at the pending-action cache marker; the job
 * re-validates everything.
 */
class BotWebhookController extends Controller
{
    public function __invoke(BotWebhookRequest $request): JsonResponse
    {
        $payload = $request->validated();

        if (array_key_exists('callback_query', $payload)) {
            ProcessTelegramCallback::dispatch($payload);
        } elseif ($this->isPendingActionReply($payload)) {
            ProcessTelegramActionReply::dispatch($payload);
        } else {
            ProcessTelegramLinkStart::dispatch($payload);
        }

        return ApiResponse::success(['accepted' => true]);
    }

    /**
     * A chat message that answers a reject/return prompt: the reply job owns
     * it while a pending action is cached for the chat; otherwise the link
     * flow keeps every plain message (it ignores non-/start text).
     *
     * @param  array<string, mixed>  $payload
     */
    private function isPendingActionReply(array $payload): bool
    {
        $chatId = (int) (($payload['message']['chat']['id'] ?? 0));

        if ($chatId === 0 || ! is_string($payload['message']['text'] ?? null)) {
            return false;
        }

        return Cache::has("telegram:approval-action:{$chatId}");
    }
}
