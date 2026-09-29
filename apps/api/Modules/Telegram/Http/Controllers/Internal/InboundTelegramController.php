<?php

namespace Modules\Telegram\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Telegram\Http\Requests\InboundTelegramRequest;
use Modules\Telegram\Jobs\ProcessInboundTelegramMessage;

/**
 * Webhook target for the TDLib service. VerifyTelegramWebhook has already
 * authenticated the HMAC — here we validate the payload, hand it to the
 * queue and answer 200 fast; all matching/persisting happens in the job.
 */
class InboundTelegramController extends Controller
{
    public function __invoke(InboundTelegramRequest $request): JsonResponse
    {
        ProcessInboundTelegramMessage::dispatch($request->validated());

        return ApiResponse::success(['accepted' => true]);
    }
}
