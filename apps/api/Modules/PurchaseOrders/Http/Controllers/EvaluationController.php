<?php

namespace Modules\PurchaseOrders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\PurchaseOrders\Http\Requests\IndexEvaluationsRequest;
use Modules\PurchaseOrders\Http\Requests\StoreEvaluationRequest;
use Modules\PurchaseOrders\Http\Requests\UpdateEvaluationRequest;
use Modules\PurchaseOrders\Http\Resources\EvaluationResource;
use Modules\PurchaseOrders\Models\Evaluation;
use Modules\PurchaseOrders\Repositories\EvaluationRepositoryInterface;
use Modules\PurchaseOrders\Services\EvaluationService;

class EvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationRepositoryInterface $repo,
        private readonly EvaluationService $service,
    ) {}

    public function index(IndexEvaluationsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Evaluation::class);

        $paginator = $this->repo->filtered(
            $request->validated(),
            (int) $request->integer('per_page', 20),
            (int) $request->integer('page', 1),
        );

        return ApiResponse::success(
            EvaluationResource::collection($paginator->getCollection())->resolve($request),
            200,
            [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function store(StoreEvaluationRequest $request): JsonResponse
    {
        $this->authorize('create', Evaluation::class);

        /** @var User $user */
        $user = $request->user();

        $evaluation = $this->service->create($request->evaluationData(), $user);

        return ApiResponse::success(
            new EvaluationResource($evaluation->load(['items', 'quotations.lines'])),
            201,
        );
    }

    public function show(Evaluation $evaluation): EvaluationResource
    {
        $this->authorize('view', $evaluation);

        return new EvaluationResource($evaluation->load(['items', 'quotations.lines']));
    }

    public function update(UpdateEvaluationRequest $request, Evaluation $evaluation): EvaluationResource
    {
        $this->authorize('update', $evaluation);

        /** @var User $user */
        $user = $request->user();

        $evaluation = $this->service->update($evaluation, $request->evaluationData(), $user);

        return new EvaluationResource($evaluation->load(['items', 'quotations.lines']));
    }

    public function destroy(Evaluation $evaluation): JsonResponse
    {
        $this->authorize('delete', $evaluation);

        $this->repo->delete($evaluation);

        return ApiResponse::success(['deleted' => true]);
    }
}
