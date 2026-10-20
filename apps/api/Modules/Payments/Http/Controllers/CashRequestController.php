<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Payments\Http\Requests\IndexCashRequestsRequest;
use Modules\Payments\Http\Requests\StoreCashRequestRequest;
use Modules\Payments\Http\Requests\UpdateCashRequestRequest;
use Modules\Payments\Http\Requests\UpdateCashRequestStatusRequest;
use Modules\Payments\Http\Resources\CashRequestResource;
use Modules\Payments\Models\CashRequest;
use Modules\Payments\Repositories\CashRequestRepositoryInterface;
use Modules\Payments\Services\CashRequestService;

class CashRequestController extends Controller
{
    public function __construct(
        private readonly CashRequestRepositoryInterface $repo,
        private readonly CashRequestService $service,
    ) {}

    public function index(IndexCashRequestsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CashRequest::class);

        $paginator = $this->repo->filtered(
            $request->validated(),
            (int) $request->integer('per_page', 20),
            (int) $request->integer('page', 1),
        );

        return ApiResponse::success(
            CashRequestResource::collection($paginator->getCollection())->resolve($request),
            200,
            [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function store(StoreCashRequestRequest $request): JsonResponse
    {
        $this->authorize('create', CashRequest::class);

        /** @var User $user */
        $user = $request->user();

        $cashRequest = $this->service->create($request->cashRequestData(), $user);

        return ApiResponse::success(
            new CashRequestResource($cashRequest->load(['lines', 'creator'])),
            201,
        );
    }

    public function show(CashRequest $cashRequest): CashRequestResource
    {
        $this->authorize('view', $cashRequest);

        return new CashRequestResource($cashRequest->load(['lines', 'creator']));
    }

    public function update(UpdateCashRequestRequest $request, CashRequest $cashRequest): CashRequestResource
    {
        $this->authorize('update', $cashRequest);

        /** @var User $user */
        $user = $request->user();

        $cashRequest = $this->service->update($cashRequest, $request->cashRequestData(), $user);

        return new CashRequestResource($cashRequest->load(['lines', 'creator']));
    }

    public function status(UpdateCashRequestStatusRequest $request, CashRequest $cashRequest): JsonResponse
    {
        $this->authorize('update', $cashRequest);

        /** @var User $user */
        $user = $request->user();

        $cashRequest = $this->service->changeStatus(
            $cashRequest,
            (string) $request->validated('status'),
            $user,
        );

        return ApiResponse::success(new CashRequestResource($cashRequest->load(['lines', 'creator'])));
    }

    public function destroy(CashRequest $cashRequest): JsonResponse
    {
        $this->authorize('delete', $cashRequest);

        $this->service->ensureDeletable($cashRequest);

        $this->repo->delete($cashRequest);

        return ApiResponse::success(['deleted' => true]);
    }
}
