<?php

namespace Modules\Telegram\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Telegram\Http\Requests\BotWebhookRequest;
use Modules\Telegram\Jobs\ProcessTelegramLinkStart;

/**
 * Webhook target for the Bot API (the /start deep-link flow).
 * VerifyTelegramBotWebhook has already authenticated the secret_token —
 * here we hand the payload to the queue and answer 200 fast; parsing and
 * redeeming the link code happens in the job.
 */
class BotWebhookController extends Controller
{
    public function __invoke(BotWebhookRequest $request): JsonResponse
    {
        ProcessTelegramLinkStart::dispatch($request->validated());

        return ApiResponse::success(['accepted' => true]);
    }
}
