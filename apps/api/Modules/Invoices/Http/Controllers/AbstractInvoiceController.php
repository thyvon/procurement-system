<?php

namespace Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Invoices\Http\Requests\IndexInvoicesRequest;
use Modules\Invoices\Http\Requests\StoreInvoiceRequest;
use Modules\Invoices\Http\Requests\UpdateInvoiceRequest;
use Modules\Invoices\Http\Requests\UpdateInvoiceStatusRequest;
use Modules\Invoices\Http\Resources\InvoiceResource;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Services\InvoiceService;

/**
 * Shared HTTP layer for the two invoice branches (direct purchase,
 * procurement officer processed). One model, one service, one table — the
 * concrete controller only pins the `type`, and that type decides which
 * documents the branch lists, creates, and is allowed to touch.
 */
abstract class AbstractInvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $repo,
        private readonly InvoiceService $service,
    ) {}

    /**
     * The single Invoice::TYPE_* this branch owns.
     */
    abstract protected function type(): string;

    public function index(IndexInvoicesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', [Invoice::class, $this->type()]);

        $filters = $request->validated();
        $filters['type'] = $this->type();

        $paginator = $this->repo->filtered(
            $filters,
            (int) $request->integer('per_page', 20),
            (int) $request->integer('page', 1),
        );

        return ApiResponse::success(
            InvoiceResource::collection($paginator->getCollection())->resolve($request),
            200,
            [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', [Invoice::class, $this->type()]);

        /** @var User $user */
        $user = $request->user();

        $invoice = $this->service->create($this->payload($request), $user);

        return ApiResponse::success(
            new InvoiceResource($invoice->load(['lines', 'creator'])),
            201,
        );
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $this->ensureOwned($invoice);
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice->load(['lines', 'creator']));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->ensureOwned($invoice);
        $this->authorize('update', $invoice);

        /** @var User $user */
        $user = $request->user();

        $invoice = $this->service->update($invoice, $this->payload($request), $user);

        return new InvoiceResource($invoice->load(['lines', 'creator']));
    }

    public function status(UpdateInvoiceStatusRequest $request, Invoice $invoice): JsonResponse
    {
        $this->ensureOwned($invoice);
        $this->authorize('update', $invoice);

        /** @var User $user */
        $user = $request->user();

        $invoice = $this->service->changeStatus(
            $invoice,
            (string) $request->validated('status'),
            $user,
        );

        return ApiResponse::success(new InvoiceResource($invoice->load(['lines', 'creator'])));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->ensureOwned($invoice);
        $this->authorize('delete', $invoice);

        $this->service->ensureDeletable($invoice);

        $this->repo->delete($invoice);

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * An invoice of another type does not exist for this branch.
     */
    protected function ensureOwned(Invoice $invoice): void
    {
        if ($invoice->type !== $this->type()) {
            abort(404);
        }
    }

    /**
     * The branch owns the type: a payload claiming another one is rejected
     * instead of being silently rewritten.
     *
     * @return array<string, mixed>
     */
    private function payload(StoreInvoiceRequest|UpdateInvoiceRequest $request): array
    {
        $data = $request->invoiceData();

        if ($data['type'] !== $this->type()) {
            throw ValidationException::withMessages([
                'type' => "This endpoint only accepts documents of type '{$this->type()}'.",
            ]);
        }

        return $data;
    }
}
