<?php

namespace Modules\Approvals\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Approvals\Http\Resources\ApprovalSettingResource;
use Modules\Approvals\Models\ApprovalSetting;

class ApprovalSettingController extends Controller
{
    /**
     * Settings are seeded per subject type and read-only for now; the
     * configuration screens need them as their vocabulary (which document
     * types can be configured) and to anchor the flows beneath each one.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ApprovalSetting::class);

        $settings = ApprovalSetting::query()
            ->orderBy('subject_type')
            ->get();

        return ApiResponse::success(ApprovalSettingResource::collection($settings));
    }
}
