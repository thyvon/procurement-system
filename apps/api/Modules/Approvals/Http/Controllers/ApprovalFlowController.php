<?php

namespace Modules\Approvals\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Approvals\Http\Requests\StoreApprovalFlowRequest;
use Modules\Approvals\Http\Requests\UpdateApprovalFlowRequest;
use Modules\Approvals\Http\Resources\ApprovalFlowResource;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Services\ApprovalFlowService;

class ApprovalFlowController extends Controller
{
    public function __construct(
        private readonly ApprovalFlowService $service,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ApprovalFlow::class);

        $flows = ApprovalFlow::query()
            ->with(['steps', 'setting'])
            ->orderBy('min_amount')
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success(ApprovalFlowResource::collection($flows));
    }

    public function store(StoreApprovalFlowRequest $request): JsonResponse
    {
        $this->authorize('create', ApprovalFlow::class);

        /** @var User $user */
        $user = $request->user();

        $flow = $this->service->create($request->validated(), $user);

        return ApiResponse::success(new ApprovalFlowResource($flow), 201);
    }

    public function show(ApprovalFlow $flow): JsonResponse
    {
        $this->authorize('view', $flow);

        return ApiResponse::success(new ApprovalFlowResource($flow->load(['steps', 'setting'])));
    }

    public function update(UpdateApprovalFlowRequest $request, ApprovalFlow $flow): JsonResponse
    {
        $this->authorize('update', $flow);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new ApprovalFlowResource($this->service->update($flow, $request->validated(), $user)));
    }

    public function destroy(ApprovalFlow $flow): JsonResponse
    {
        $this->authorize('delete', $flow);

        $this->service->destroy($flow);

        return ApiResponse::success(['deleted' => true]);
    }
}
