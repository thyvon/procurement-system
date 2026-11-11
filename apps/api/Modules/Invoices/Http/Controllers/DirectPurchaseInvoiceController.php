<?php

namespace Modules\Invoices\Http\Controllers;

use Modules\Invoices\Models\Invoice;

/**
 * The `direct_purchase` branch (INV) — the requester bought the materials
 * or service themselves and the invoice settles that purchase.
 */
class DirectPurchaseInvoiceController extends AbstractInvoiceController
{
    protected function type(): string
    {
        return Invoice::TYPE_DIRECT_PURCHASE;
    }
}
