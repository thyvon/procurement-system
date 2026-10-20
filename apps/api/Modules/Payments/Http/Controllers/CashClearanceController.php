<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Payments\Http\Requests\IndexCashClearancesRequest;
use Modules\Payments\Http\Requests\StoreCashClearanceRequest;
use Modules\Payments\Http\Requests\UpdateCashClearanceRequest;
use Modules\Payments\Http\Requests\UpdateCashClearanceStatusRequest;
use Modules\Payments\Http\Resources\CashClearanceResource;
use Modules\Payments\Models\CashClearance;
use Modules\Payments\Repositories\CashClearanceRepositoryInterface;
use Modules\Payments\Services\CashClearanceService;

class CashClearanceController extends Controller
{
    public function __construct(
        private readonly CashClearanceRepositoryInterface $repo,
        private readonly CashClearanceService $service,
    ) {}

    public function index(IndexCashClearancesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CashClearance::class);

        $paginator = $this->repo->filtered(
            $request->validated(),
            (int) $request->integer('per_page', 20),
            (int) $request->integer('page', 1),
        );

        return ApiResponse::success(
            CashClearanceResource::collection($paginator->getCollection())->resolve($request),
            200,
            [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function store(StoreCashClearanceRequest $request): JsonResponse
    {
        $this->authorize('create', CashClearance::class);

        /** @var User $user */
        $user = $request->user();

        $cashClearance = $this->service->create($request->cashClearanceData(), $user);

        return ApiResponse::success(
            new CashClearanceResource($cashClearance->load(['lines', 'creator'])),
            201,
        );
    }

    public function show(CashClearance $cashClearance): CashClearanceResource
    {
        $this->authorize('view', $cashClearance);

        return new CashClearanceResource($cashClearance->load(['lines', 'creator']));
    }

    public function update(UpdateCashClearanceRequest $request, CashClearance $cashClearance): CashClearanceResource
    {
        $this->authorize('update', $cashClearance);

        /** @var User $user */
        $user = $request->user();

        $cashClearance = $this->service->update($cashClearance, $request->cashClearanceData(), $user);

        return new CashClearanceResource($cashClearance->load(['lines', 'creator']));
    }

    public function status(UpdateCashClearanceStatusRequest $request, CashClearance $cashClearance): JsonResponse
    {
        $this->authorize('update', $cashClearance);

        /** @var User $user */
        $user = $request->user();

        $cashClearance = $this->service->changeStatus(
            $cashClearance,
            (string) $request->validated('status'),
            $user,
        );

        return ApiResponse::success(new CashClearanceResource($cashClearance->load(['lines', 'creator'])));
    }

    public function destroy(CashClearance $cashClearance): JsonResponse
    {
        $this->authorize('delete', $cashClearance);

        $this->service->ensureDeletable($cashClearance);

        $this->repo->delete($cashClearance);

        return ApiResponse::success(['deleted' => true]);
    }
}
