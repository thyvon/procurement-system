<?php

namespace Modules\Telegram\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Organization\Models\SystemSetting;
use Modules\Telegram\Services\TelegramWebhookService;

/**
 * Reports and updates the Bot API webhook registration (setWebhook).
 *
 * This is settings-surface state — the Settings → Telegram tab owns it —
 * so it reuses SystemSettingPolicy: read with settings.view, register with
 * settings.manage, exactly like SystemSettingController.
 */
class TelegramWebhookController extends Controller
{
    public function index(TelegramWebhookService $service): JsonResponse
    {
        $this->authorize('viewAny', SystemSetting::class);

        return ApiResponse::success($service->status());
    }

    public function store(TelegramWebhookService $service): JsonResponse
    {
        $this->authorize('update', SystemSetting::class);

        return ApiResponse::success($service->register());
    }
}
