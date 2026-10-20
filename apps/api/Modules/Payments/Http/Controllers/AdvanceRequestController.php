<?php

namespace Modules\Payments\Http\Controllers;

use Modules\Payments\Models\CashRequest;

/**
 * The `advance` branch (AD) — the document an Advance Liquidation settles.
 */
class AdvanceRequestController extends AbstractCashRequestController
{
    protected function type(): string
    {
        return CashRequest::TYPE_ADVANCE;
    }
}
