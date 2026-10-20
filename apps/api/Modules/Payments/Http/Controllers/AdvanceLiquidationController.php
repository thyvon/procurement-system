<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Payments\Http\Requests\IndexAdvanceLiquidationsRequest;
use Modules\Payments\Http\Requests\StoreAdvanceLiquidationRequest;
use Modules\Payments\Http\Requests\UpdateAdvanceLiquidationRequest;
use Modules\Payments\Http\Requests\UpdateAdvanceLiquidationStatusRequest;
use Modules\Payments\Http\Resources\AdvanceLiquidationResource;
use Modules\Payments\Models\AdvanceLiquidation;
use Modules\Payments\Repositories\AdvanceLiquidationRepositoryInterface;
use Modules\Payments\Services\AdvanceLiquidationService;

class AdvanceLiquidationController extends Controller
{
    public function __construct(
        private readonly AdvanceLiquidationRepositoryInterface $repo,
        private readonly AdvanceLiquidationService $service,
    ) {}

    public function index(IndexAdvanceLiquidationsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AdvanceLiquidation::class);

        $paginator = $this->repo->filtered(
            $request->validated(),
            (int) $request->integer('per_page', 20),
            (int) $request->integer('page', 1),
        );

        return ApiResponse::success(
            AdvanceLiquidationResource::collection($paginator->getCollection())->resolve($request),
            200,
            [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function store(StoreAdvanceLiquidationRequest $request): JsonResponse
    {
        $this->authorize('create', AdvanceLiquidation::class);

        /** @var User $user */
        $user = $request->user();

        $advanceLiquidation = $this->service->create($request->advanceLiquidationData(), $user);

        return ApiResponse::success(
            new AdvanceLiquidationResource($advanceLiquidation->load(['lines', 'creator'])),
            201,
        );
    }

    public function show(AdvanceLiquidation $advanceLiquidation): AdvanceLiquidationResource
    {
        $this->authorize('view', $advanceLiquidation);

        return new AdvanceLiquidationResource($advanceLiquidation->load(['lines', 'creator']));
    }

    public function update(UpdateAdvanceLiquidationRequest $request, AdvanceLiquidation $advanceLiquidation): AdvanceLiquidationResource
    {
        $this->authorize('update', $advanceLiquidation);

        /** @var User $user */
        $user = $request->user();

        $advanceLiquidation = $this->service->update($advanceLiquidation, $request->advanceLiquidationData(), $user);

        return new AdvanceLiquidationResource($advanceLiquidation->load(['lines', 'creator']));
    }

    public function status(UpdateAdvanceLiquidationStatusRequest $request, AdvanceLiquidation $advanceLiquidation): JsonResponse
    {
        $this->authorize('update', $advanceLiquidation);

        /** @var User $user */
        $user = $request->user();

        $advanceLiquidation = $this->service->changeStatus(
            $advanceLiquidation,
            (string) $request->validated('status'),
            $user,
        );

        return ApiResponse::success(new AdvanceLiquidationResource($advanceLiquidation->load(['lines', 'creator'])));
    }

    public function destroy(AdvanceLiquidation $advanceLiquidation): JsonResponse
    {
        $this->authorize('delete', $advanceLiquidation);

        $this->service->ensureDeletable($advanceLiquidation);

        $this->repo->delete($advanceLiquidation);

        return ApiResponse::success(['deleted' => true]);
    }
}
