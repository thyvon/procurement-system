<?php

namespace Modules\Telegram\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Telegram\Services\TelegramLinkService;

/**
 * Issues the personal deep link (t.me/<bot>?start=<code>) that binds the
 * caller's direct chat to users.telegram_chat_id through the /start flow.
 * Self-service: every authenticated user manages their own link, so no
 * policy applies (nothing cross-user is readable here).
 */
class TelegramLinkController extends Controller
{
    public function __invoke(Request $request, TelegramLinkService $linkService): JsonResponse
    {
        $username = ltrim((string) config('services.telegram.bot_username'), '@');

        if ($username === '') {
            abort(503, 'Telegram bot is not configured.');
        }

        /** @var User $user */
        $user = $request->user();

        $link = $linkService->issue($user);

        return ApiResponse::success([
            'url' => "https://t.me/{$username}?start={$link->code}",
            'code' => $link->code,
            'expiresAt' => $link->expires_at->toIso8601String(),
        ]);
    }
}
