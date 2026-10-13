<?php

namespace Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Invoices\Http\Requests\IndexInvoicesRequest;
use Modules\Invoices\Http\Requests\StoreInvoiceRequest;
use Modules\Invoices\Http\Requests\UpdateInvoiceRequest;
use Modules\Invoices\Http\Requests\UpdateInvoiceStatusRequest;
use Modules\Invoices\Http\Resources\InvoiceResource;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Services\InvoiceService;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $repo,
        private readonly InvoiceService $service,
    ) {}

    public function index(IndexInvoicesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $paginator = $this->repo->filtered(
            $request->validated(),
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
        $this->authorize('create', Invoice::class);

        /** @var User $user */
        $user = $request->user();

        $invoice = $this->service->create($request->invoiceData(), $user);

        return ApiResponse::success(
            new InvoiceResource($invoice->load(['lines', 'creator'])),
            201,
        );
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice->load(['lines', 'creator']));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('update', $invoice);

        /** @var User $user */
        $user = $request->user();

        $invoice = $this->service->update($invoice, $request->invoiceData(), $user);

        return new InvoiceResource($invoice->load(['lines', 'creator']));
    }

    public function status(UpdateInvoiceStatusRequest $request, Invoice $invoice): JsonResponse
    {
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
        $this->authorize('delete', $invoice);

        $this->service->ensureDeletable($invoice);

        $this->repo->delete($invoice);

        return ApiResponse::success(['deleted' => true]);
    }
}
