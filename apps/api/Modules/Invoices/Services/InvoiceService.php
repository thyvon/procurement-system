<?php

namespace Modules\Invoices\Services;

use App\Models\User;
use App\Support\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Models\InvoiceLine;
use Modules\Payments\Models\CashRequest;
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
                'supplier_phone' => $data['supplier_phone'] ?? null,
                'supplier_address' => $data['supplier_address'] ?? null,
                'transaction_type' => $data['transaction_type'] ?? null,
                ...$this->resolveCashLink($data),
                'paid_at' => $this->derivePaidAt($data),
                'payment_term' => $data['payment_term'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => $data['currency'] ?? Currency::USD,
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'subtotal' => 0,
                'vat_amount' => 0,
                'vat_percent' => $data['vat_percent'] ?? null,
                'discount_amount' => 0,
                'total' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            $this->syncLines($invoice, $data, $user);

            $this->ensureWithinCashRequestBalance($invoice);

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
                'supplier_phone' => $data['supplier_phone'] ?? null,
                'supplier_address' => $data['supplier_address'] ?? null,
                'transaction_type' => $data['transaction_type'] ?? null,
                ...$this->resolveCashLink($data),
                'paid_at' => $this->derivePaidAt($data),
                'payment_term' => $data['payment_term'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => $data['currency'] ?? $invoice->currency,
                'exchange_rate' => $data['exchange_rate'] ?? $invoice->exchange_rate,
                'vat_amount' => $data['vat_amount'] ?? 0,
                'vat_percent' => $data['vat_percent'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'updated_by' => $user->getKey(),
            ]);

            // Wholesale replace: forceDelete so re-entered lines never collide
            // with soft-deleted rows on a future unique index.
            $invoice->lines()->forceDelete();

            $this->syncLines($invoice, $data, $user);

            $this->ensureWithinCashRequestBalance($invoice);

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
     * `paid_at` is derived, never client-accepted: cash-settled invoices
     * (petty_cash, advance) are paid on their invoice date; credit invoices
     * are not paid yet. Recomputed on every write so switching the
     * transaction type re-stamps (or clears) it.
     *
     * @param  array<string, mixed>  $data
     */
    private function derivePaidAt(array $data): ?string
    {
        return match ($data['transaction_type'] ?? null) {
            Invoice::TRANSACTION_TYPE_PETTY_CASH, Invoice::TRANSACTION_TYPE_ADVANCE => $data['invoice_date'],
            default => null,
        };
    }

    /**
     * The cash link is server-owned: a cash-settled invoice stores the
     * referenced request's id and derives `cash_reference` from its code
     * (a reference must point at an existing request of the same type);
     * a credit invoice — or a type switch to credit — clears both. Like
     * `paid_at`, it is recomputed on every write.
     *
     * @param  array<string, mixed>  $data
     * @return array{cash_request_id: string|null, cash_reference: string|null}
     */
    private function resolveCashLink(array $data): array
    {
        $requestId = $data['cash_request_id'] ?? null;

        if ($data['transaction_type'] === Invoice::TRANSACTION_TYPE_CREDIT || $requestId === null) {
            return ['cash_request_id' => null, 'cash_reference' => null];
        }

        $cashRequest = CashRequest::query()->find($requestId);

        if ($cashRequest === null) {
            throw ValidationException::withMessages([
                'cash_request_id' => 'Record not found.',
            ]);
        }

        if ($cashRequest->type !== $data['transaction_type']) {
            throw ValidationException::withMessages([
                'cash_request_id' => "An invoice of type '{$data['transaction_type']}' cannot reference a '{$cashRequest->type}' cash request.",
            ]);
        }

        return [
            'cash_request_id' => $cashRequest->getKey(),
            'cash_reference' => $cashRequest->code,
        ];
    }

    /**
     * The spend cap: a cash-settled invoice may not push the sum of its
     * request's non-cancelled invoices past the request's approved total.
     * This invoice is excluded from the sum, so an update that repoints the
     * link frees the old request's budget automatically. Runs inside the
     * write transaction (after syncLines has written the final totals), so
     * a violation rolls the whole write back.
     */
    private function ensureWithinCashRequestBalance(Invoice $invoice): void
    {
        if ($invoice->cash_request_id === null) {
            return;
        }

        $cashRequest = CashRequest::query()->find($invoice->cash_request_id);

        if ($cashRequest === null) {
            return;
        }

        $usedUsd = (float) Invoice::query()
            ->where('cash_request_id', $cashRequest->getKey())
            ->whereKeyNot($invoice->getKey())
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->sum('grand_total_usd');

        $invoiceUsd = (float) $invoice->grand_total_usd;
        $approvedUsd = (float) $cashRequest->total_usd;

        if (round($usedUsd + $invoiceUsd, 2) > $approvedUsd) {
            throw ValidationException::withMessages([
                'cash_request_id' => sprintf(
                    'Invoice total (%s USD) exceeds cash request %s: approved %s USD, already used %s USD, remaining %s USD.',
                    number_format($invoiceUsd, 2, '.', ''),
                    $cashRequest->code,
                    number_format($approvedUsd, 2, '.', ''),
                    number_format($usedUsd, 2, '.', ''),
                    number_format(max($approvedUsd - $usedUsd, 0), 2, '.', ''),
                ),
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
                'uom' => $line['uom'] ?? null,
                'campus' => $line['campus'] ?? null,
                'division' => $line['division'] ?? null,
                'department' => $line['department'] ?? null,
                'location' => $line['location'] ?? null,
                'purpose' => $line['purpose'] ?? null,
                'qty' => $line['qty'],
                'unit_price' => $line['unit_price'],
                'discount' => $discount,
                'line_total' => $lineTotal,
                'grand_total_usd' => $this->convertToUsd($invoice, $lineTotal),
                'position' => $position,
            ]);
        }

        $discount = round((float) ($data['discount_amount'] ?? 0), 2);
        $subtotal = round($subtotal, 2);
        $vat = $this->resolveVat($data, $subtotal, $discount);
        $total = round($subtotal - $discount + $vat, 2);

        $invoice->update([
            'subtotal' => $subtotal,
            'vat_amount' => round($vat, 2),
            'discount_amount' => round($discount, 2),
            'total' => $total,
            'grand_total_usd' => $this->convertToUsd($invoice, $total),
            'updated_by' => $user->getKey(),
        ]);
    }

    /**
     * The stored amount snapshot is derived from the client-entered percentage
     * of the discounted subtotal; legacy payloads without a percent keep the
     * submitted amount (invoiceData() always carries both keys).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveVat(array $data, float $subtotal, float $discount): float
    {
        $percent = $data['vat_percent'] ?? null;

        if ($percent === null) {
            return round((float) ($data['vat_amount'] ?? 0), 2);
        }

        return round(($subtotal - $discount) * (float) $percent / 100, 2);
    }

    /**
     * USD equivalent of an amount on this invoice, converted through the
     * document's own currency and entry-time exchange rate. Persisted so
     * dashboards can sum USD without re-deriving every row.
     */
    private function convertToUsd(Invoice $invoice, float $amount): float
    {
        return Currency::toUsd($amount, (string) $invoice->currency, (float) $invoice->exchange_rate);
    }
}
