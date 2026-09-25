<?php

namespace Modules\Approvals\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Modules\Approvals\Http\Requests\IndexApprovalRequestsRequest;
use Modules\Approvals\Http\Requests\PreviewApprovalRequest;
use Modules\Approvals\Http\Requests\StoreApprovalActionRequest;
use Modules\Approvals\Http\Requests\StoreApprovalRequest;
use Modules\Approvals\Http\Resources\ApprovalRequestResource;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Repositories\ApprovalRequestRepositoryInterface;
use Modules\Approvals\Services\ApprovalService;

class ApprovalRequestController extends Controller
{
    public function __construct(
        private readonly ApprovalRequestRepositoryInterface $repo,
        private readonly ApprovalService $service,
    ) {}

    public function preview(PreviewApprovalRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        return ApiResponse::success(
            $this->service->preview(
                $request->string('subject_type')->toString(),
                $request->string('subject_id')->toString(),
            ),
        );
    }

    public function index(IndexApprovalRequestsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        $paginator = $this->repo->filtered(
            $request->validated(),
            (int) $request->integer('per_page', 20),
            (int) $request->integer('page', 1),
        );

        return $this->paginated($request, $paginator);
    }

    public function store(StoreApprovalRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $subjectType = $request->string('subject_type')->toString();

        $this->authorize('submit', [ApprovalRequest::class, $subjectType]);

        $approval = $this->service->submit(
            $subjectType,
            $request->string('subject_id')->toString(),
            $request->validated('assignees'),
            $user,
        );

        return ApiResponse::success(new ApprovalRequestResource($approval), 201);
    }

    public function show(ApprovalRequest $request): ApprovalRequestResource
    {
        $this->authorize('view', $request);

        return new ApprovalRequestResource($request->load(['actions.actor', 'creator']));
    }

    public function actions(StoreApprovalActionRequest $request, ApprovalRequest $approval): JsonResponse
    {
        $this->authorize('act', $approval);

        /** @var User $user */
        $user = $request->user();

        $approval = $this->service->act(
            $approval,
            $request->string('action')->toString(),
            $request->validated('comment'),
            $user,
        );

        return ApiResponse::success(new ApprovalRequestResource($approval->load(['actions.actor', 'creator'])));
    }

    public function inbox(IndexApprovalRequestsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        /** @var User $user */
        $user = $request->user();

        $paginator = $this->repo->inbox(
            (int) $user->getKey(),
            $request->validated(),
            (int) $request->integer('per_page', 20),
            (int) $request->integer('page', 1),
        );

        return $this->paginated($request, $paginator);
    }

    public function outbox(IndexApprovalRequestsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        /** @var User $user */
        $user = $request->user();

        $paginator = $this->repo->outbox(
            (int) $user->getKey(),
            $request->validated(),
            (int) $request->integer('per_page', 20),
            (int) $request->integer('page', 1),
        );

        return $this->paginated($request, $paginator);
    }

    public function inboxCount(IndexApprovalRequestsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(['count' => $this->repo->pendingCount((int) $user->getKey())]);
    }

    private function paginated(IndexApprovalRequestsRequest $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return ApiResponse::success(
            ApprovalRequestResource::collection($paginator->getCollection())->resolve($request),
            200,
            [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }
}
