<?php

namespace Modules\Invoices\Http\Controllers;

use Modules\Invoices\Models\Invoice;

/**
 * The `pro_purchase_invoice` branch (PI) — the procurement officer ran the
 * purchase and records the resulting supplier invoice.
 */
class ProPurchaseInvoiceController extends AbstractInvoiceController
{
    protected function type(): string
    {
        return Invoice::TYPE_PRO_PURCHASE_INVOICE;
    }
}
