<?php

namespace Modules\Payments\Http\Controllers;

use Modules\Payments\Models\CashRequest;

/**
 * The `petty_cash` branch (PC) — the document a Cash Clearance settles.
 */
class PettyCashRequestController extends AbstractCashRequestController
{
    protected function type(): string
    {
        return CashRequest::TYPE_PETTY_CASH;
    }
}
