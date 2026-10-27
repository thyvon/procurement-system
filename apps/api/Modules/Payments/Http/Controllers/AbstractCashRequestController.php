<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Payments\Http\Requests\IndexCashRequestsRequest;
use Modules\Payments\Http\Requests\StoreCashRequestRequest;
use Modules\Payments\Http\Requests\UpdateCashRequestRequest;
use Modules\Payments\Http\Requests\UpdateCashRequestStatusRequest;
use Modules\Payments\Http\Resources\CashRequestResource;
use Modules\Payments\Models\CashRequest;
use Modules\Payments\Repositories\CashRequestRepositoryInterface;
use Modules\Payments\Services\CashRequestService;
use Modules\Payments\Services\PaymentPrintService;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Shared HTTP layer for the three cash request branches (petty cash, advance,
 * cash). One model, one service, one table — the concrete controller only
 * pins the `type`, and that type decides which documents the branch lists,
 * creates, and is allowed to touch.
 */
abstract class AbstractCashRequestController extends Controller
{
    public function __construct(
        private readonly CashRequestRepositoryInterface $repo,
        private readonly CashRequestService $service,
        private readonly PaymentPrintService $print,
    ) {}

    /**
     * The single CashRequest::TYPE_* this branch owns.
     */
    abstract protected function type(): string;

    public function index(IndexCashRequestsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CashRequest::class);

        $filters = $request->validated();
        $filters['type'] = $this->type();

        $paginator = $this->repo->filtered(
            $filters,
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

        $cashRequest = $this->service->create($this->payload($request), $user);

        return ApiResponse::success(
            new CashRequestResource($cashRequest->load(['lines', 'creator'])),
            201,
        );
    }

    public function show(CashRequest $cashRequest): CashRequestResource
    {
        $this->ensureOwned($cashRequest);
        $this->authorize('view', $cashRequest);

        return new CashRequestResource($cashRequest->load(['lines', 'creator']));
    }

    public function print(Request $request, CashRequest $cashRequest): PdfBuilder
    {
        $this->ensureOwned($cashRequest);
        $this->authorize('view', $cashRequest);

        /** @var User $user */
        $user = $request->user();

        return $this->print->forCashRequest($cashRequest, $user);
    }

    public function update(UpdateCashRequestRequest $request, CashRequest $cashRequest): CashRequestResource
    {
        $this->ensureOwned($cashRequest);
        $this->authorize('update', $cashRequest);

        /** @var User $user */
        $user = $request->user();

        $cashRequest = $this->service->update($cashRequest, $this->payload($request), $user);

        return new CashRequestResource($cashRequest->load(['lines', 'creator']));
    }

    public function status(UpdateCashRequestStatusRequest $request, CashRequest $cashRequest): JsonResponse
    {
        $this->ensureOwned($cashRequest);
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
        $this->ensureOwned($cashRequest);
        $this->authorize('delete', $cashRequest);

        $this->service->ensureDeletable($cashRequest);

        $this->repo->delete($cashRequest);

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * A document of another type does not exist for this branch.
     */
    protected function ensureOwned(CashRequest $cashRequest): void
    {
        if ($cashRequest->type !== $this->type()) {
            abort(404);
        }
    }

    /**
     * The branch owns the type: a payload claiming another one is rejected
     * instead of being silently rewritten.
     *
     * @return array<string, mixed>
     */
    private function payload(StoreCashRequestRequest|UpdateCashRequestRequest $request): array
    {
        $data = $request->cashRequestData();

        if ($data['type'] !== $this->type()) {
            throw ValidationException::withMessages([
                'type' => "This endpoint only accepts documents of type '{$this->type()}'.",
            ]);
        }

        return $data;
    }
}
