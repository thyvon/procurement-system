<?php

namespace Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Organization\Http\Requests\UpdateSystemSettingRequest;
use Modules\Organization\Models\SystemSetting;
use Modules\Organization\Services\SystemSettingsService;

class SystemSettingController extends Controller
{
    public function __construct(private readonly SystemSettingsService $settings) {}

    public function index(string $group): JsonResponse
    {
        abort_unless($this->settings->definitions($group) !== [], 404);

        $this->authorize('viewAny', SystemSetting::class);

        return ApiResponse::success($this->settings->values($group));
    }

    public function update(UpdateSystemSettingRequest $request, string $group): JsonResponse
    {
        abort_unless($this->settings->definitions($group) !== [], 404);

        $this->authorize('update', SystemSetting::class);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->settings->update($group, (array) ($request->validated('values') ?? []), $user),
        );
    }
}
