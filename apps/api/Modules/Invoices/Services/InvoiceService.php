<?php

namespace Modules\Invoices\Services;

use App\Models\User;
use App\Support\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Models\InvoiceLine;
use Modules\Products\Services\CodeGenerationService;

/**
 * Owns every write on an invoice: server-generated code, the line list and
 * the derived money columns, and the draft → recorded → cancelled lifecycle.
 * The client may only suggest lines and header inputs; totals are always
 * recomputed here so the stored document is internally consistent.
 */
class InvoiceService
{
    /**
     * Allowed status moves. `cancelled` is terminal and `draft` is the only
     * state whose contents can still change.
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        Invoice::STATUS_DRAFT => [Invoice::STATUS_RECORDED, Invoice::STATUS_CANCELLED],
        Invoice::STATUS_RECORDED => [Invoice::STATUS_CANCELLED],
        Invoice::STATUS_CANCELLED => [],
    ];

    public function __construct(private readonly CodeGenerationService $codes) {}

    /**
     * @param  array<string, mixed>  $data  from invoiceData()
     */
    public function create(array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($data, $user): Invoice {
            $invoice = Invoice::create([
                'entity_id' => $user->entity_id,
                'code' => $this->codes->next('INV', Invoice::class, $user->entity_id),
                'status' => Invoice::STATUS_DRAFT,
                'invoice_number' => $data['invoice_number'],
                'supplier_name' => $data['supplier_name'],
                'supplier_code' => $data['supplier_code'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => $data['currency'] ?? Currency::USD,
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'subtotal' => 0,
                'vat_amount' => 0,
                'discount_amount' => 0,
                'total' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            $this->syncLines($invoice, $data, $user);

            return $invoice->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  from invoiceData()
     */
    public function update(Invoice $invoice, array $data, User $user): Invoice
    {
        $this->ensureEditable($invoice);

        return DB::transaction(function () use ($invoice, $data, $user): Invoice {
            $invoice->update([
                'invoice_number' => $data['invoice_number'],
                'supplier_name' => $data['supplier_name'],
                'supplier_code' => $data['supplier_code'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => $data['currency'] ?? $invoice->currency,
                'exchange_rate' => $data['exchange_rate'] ?? $invoice->exchange_rate,
                'vat_amount' => $data['vat_amount'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'updated_by' => $user->getKey(),
            ]);

            // Wholesale replace: forceDelete so re-entered lines never collide
            // with soft-deleted rows on a future unique index.
            $invoice->lines()->forceDelete();

            $this->syncLines($invoice, $data, $user);

            return $invoice->refresh();
        });
    }

    public function changeStatus(Invoice $invoice, string $status, User $user): Invoice
    {
        $allowed = self::TRANSITIONS[$invoice->status] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "An invoice with status '{$invoice->status}' cannot move to '{$status}'.",
            ]);
        }

        $invoice->update([
            'status' => $status,
            'updated_by' => $user->getKey(),
        ]);

        return $invoice->refresh();
    }

    /**
     * Only drafts are editable: once an invoice is recorded it is the
     * bookkeeping record finance works from, and a cancelled one is closed.
     */
    public function ensureEditable(Invoice $invoice): void
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => "An invoice with status '{$invoice->status}' cannot be modified.",
            ]);
        }
    }

    public function ensureDeletable(Invoice $invoice): void
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => "Only a draft invoice can be deleted, this one is '{$invoice->status}'.",
            ]);
        }
    }

    /**
     * Rewrite the line list and recompute every derived money column.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncLines(Invoice $invoice, array $data, User $user): void
    {
        $subtotal = 0.0;

        foreach (array_values($data['lines']) as $position => $line) {
            $gross = round((float) $line['qty'] * (float) $line['unit_price'], 2);
            $discount = round((float) ($line['discount'] ?? 0), 2);

            if ($discount > $gross) {
                throw ValidationException::withMessages([
                    "lines.{$position}.discount" => 'The discount cannot exceed the line amount.',
                ]);
            }

            // line_total is net of the per-line discount; the header discount
            // is applied once more on the invoice total.
            $lineTotal = round($gross - $discount, 2);
            $subtotal += $lineTotal;

            InvoiceLine::create([
                'entity_id' => $invoice->entity_id,
                'invoice_id' => $invoice->getKey(),
                'description' => $line['description'],
                'qty' => $line['qty'],
                'unit_price' => $line['unit_price'],
                'discount' => $discount,
                'line_total' => $lineTotal,
                'position' => $position,
            ]);
        }

        $vat = (float) ($data['vat_amount'] ?? 0);
        $discount = (float) ($data['discount_amount'] ?? 0);
        $subtotal = round($subtotal, 2);

        $invoice->update([
            'subtotal' => $subtotal,
            'vat_amount' => round($vat, 2),
            'discount_amount' => round($discount, 2),
            'total' => round($subtotal - $discount + $vat, 2),
            'updated_by' => $user->getKey(),
        ]);
    }
}
