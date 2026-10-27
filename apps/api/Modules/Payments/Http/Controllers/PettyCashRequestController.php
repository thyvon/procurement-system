<?php

namespace Modules\Payments\Http\Controllers;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Payments\Models\CashRequest;
use Modules\Payments\Repositories\CashRequestRepositoryInterface;
use Modules\Payments\Services\CashClearanceService;
use Modules\Payments\Services\CashRequestService;
use Modules\Payments\Services\PaymentPrintService;

/**
 * The `petty_cash` branch (PC) — the document a Cash Clearance settles.
 */
class PettyCashRequestController extends AbstractCashRequestController
{
    public function __construct(
        CashRequestRepositoryInterface $repo,
        CashRequestService $service,
        PaymentPrintService $print,
        private readonly CashClearanceService $clearances,
    ) {
        parent::__construct($repo, $service, $print);
    }

    protected function type(): string
    {
        return CashRequest::TYPE_PETTY_CASH;
    }

    /**
     * The invoice items linked to this request, shaped as suggested cash
     * clearance lines: the prefill the clearance form applies when the user
     * picks the request.
     */
    public function invoiceItems(CashRequest $cashRequest): JsonResponse
    {
        $this->ensureOwned($cashRequest);
        $this->authorize('view', $cashRequest);

        return ApiResponse::success($this->clearances->sourceItems($cashRequest));
    }
}
