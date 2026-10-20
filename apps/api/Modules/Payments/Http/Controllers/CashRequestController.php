<?php

namespace Modules\Payments\Http\Controllers;

use Modules\Payments\Models\CashRequest;

/**
 * The `cash` branch (CR): a cash request settles its own payment, so this
 * endpoint has no follow-up clearance or liquidation document.
 */
class CashRequestController extends AbstractCashRequestController
{
    protected function type(): string
    {
        return CashRequest::TYPE_CASH;
    }
}
