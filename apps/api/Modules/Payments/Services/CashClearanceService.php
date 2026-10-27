<?php

namespace Modules\Payments\Services;

use App\Models\User;
use App\Support\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Invoices\Models\Invoice;
use Modules\Payments\Models\CashClearance;
use Modules\Payments\Models\CashClearanceLine;
use Modules\Payments\Models\CashRequest;
use Modules\Products\Services\CodeGenerationService;

/**
 * Owns every write on a cash clearance: the approved-source and
 * one-final-clearance guards, the server-generated PCC code, the line list
 * and the derived money columns (total, balance). The client may only suggest
 * lines and header inputs; totals are always recomputed here so the stored
 * document is internally consistent.
 */
class CashClearanceService
{
    /**
     * Allowed status moves. `cancelled` is terminal, and only `draft` /
     * `returned` documents can still change — the approval engine owns every
     * other transition.
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        CashClearance::STATUS_DRAFT => [CashClearance::STATUS_CANCELLED],
        CashClearance::STATUS_CANCELLED => [],
    ];

    public function __construct(private readonly CodeGenerationService $codes) {}

    /**
     * @param  array<string, mixed>  $data  from cashClearanceData()
     */
    public function create(array $data, User $user): CashClearance
    {
        return DB::transaction(function () use ($data, $user): CashClearance {
            $source = $this->resolveSource($data);

            $this->ensureApprovedSource($source);
            $this->ensureNoOtherClearance($source, null);

            $cashClearance = CashClearance::create([
                'entity_id' => $user->entity_id,
                // No campus segment: a clearance is per line, not per campus.
                'code' => $this->codes->nextWithCampus(
                    'PCC',
                    CashClearance::class,
                    $user->entity_id,
                    null,
                    Carbon::parse($data['clearance_date']),
                ),
                'status' => CashClearance::STATUS_DRAFT,
                'cash_request_id' => $source->getKey(),
                'clearance_date' => $data['clearance_date'],
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'total_usd' => 0,
                'request_amount_usd' => (float) $source->total_usd,
                'balance_usd' => 0,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            $this->syncLines($cashClearance, $data, $user);

            return $cashClearance->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  from cashClearanceData()
     */
    public function update(CashClearance $cashClearance, array $data, User $user): CashClearance
    {
        $this->ensureEditable($cashClearance);

        return DB::transaction(function () use ($cashClearance, $data, $user): CashClearance {
            $source = $this->resolveSource($data);

            // Updates repoint the clearance like a create does, so both entry
            // guards run again (excluding this clearance from the check).
            $this->ensureApprovedSource($source);
            $this->ensureNoOtherClearance($source, $cashClearance);

            $cashClearance->update([
                'cash_request_id' => $source->getKey(),
                'clearance_date' => $data['clearance_date'],
                'exchange_rate' => $data['exchange_rate'] ?? $cashClearance->exchange_rate,
                'request_amount_usd' => (float) $source->total_usd,
                'updated_by' => $user->getKey(),
            ]);

            // Wholesale replace: forceDelete so re-entered lines never collide
            // with soft-deleted rows on a future unique index.
            $cashClearance->lines()->forceDelete();

            $this->syncLines($cashClearance, $data, $user);

            return $cashClearance->refresh();
        });
    }

    public function changeStatus(CashClearance $cashClearance, string $status, User $user): CashClearance
    {
        $allowed = self::TRANSITIONS[$cashClearance->status] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "A cash clearance with status '{$cashClearance->status}' cannot move to '{$status}'.",
            ]);
        }

        $cashClearance->update([
            'status' => $status,
            'updated_by' => $user->getKey(),
        ]);

        return $cashClearance->refresh();
    }

    /**
     * Only drafts and returned documents are editable: once a clearance is in
     * review it belongs to the approval flow, and a decided or cancelled one
     * is closed.
     */
    public function ensureEditable(CashClearance $cashClearance): void
    {
        if (! in_array($cashClearance->status, [CashClearance::STATUS_DRAFT, CashClearance::STATUS_RETURNED], true)) {
            throw ValidationException::withMessages([
                'status' => "A cash clearance with status '{$cashClearance->status}' cannot be modified.",
            ]);
        }
    }

    public function ensureDeletable(CashClearance $cashClearance): void
    {
        if ($cashClearance->status !== CashClearance::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => "Only a draft cash clearance can be deleted, this one is '{$cashClearance->status}'.",
            ]);
        }
    }

    /**
     * Suggested clearance lines for a cash request: every non-cancelled
     * invoice linked to it (FK), flattened into the line payload the client
     * submits. VAT lives at header level on an invoice, so it is split across
     * that invoice's lines by net-total share (remainder on the last line),
     * and every amount is converted to USD through the invoice's own currency
     * and entry-time rate.
     *
     * @return array<int, array{
     *     invoiceDate: string|null,
     *     prRef: string|null,
     *     purchaser: string|null,
     *     description: string,
     *     vendor: string,
     *     vendorPhone: string|null,
     *     campus: string|null,
     *     division: string|null,
     *     department: string|null,
     *     qty: float,
     *     uom: string|null,
     *     unitPriceUsd: float,
     *     discountUsd: float,
     *     vatUsd: float,
     * }>
     */
    public function sourceItems(CashRequest $source): array
    {
        $invoices = Invoice::query()
            ->where('entity_id', $source->entity_id)
            ->where('cash_request_id', $source->getKey())
            ->where('transaction_type', $source->type)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->with(['lines' => fn ($query) => $query->orderBy('position')])
            ->orderBy('invoice_date')
            ->orderBy('code')
            ->get();

        $rows = [];

        foreach ($invoices as $invoice) {
            $currency = (string) $invoice->currency;
            $rate = (float) $invoice->exchange_rate;
            $vatUsd = Currency::toUsd((float) $invoice->vat_amount, $currency, $rate);
            $shares = $this->allocateVat($invoice, $vatUsd);

            foreach ($invoice->lines as $index => $line) {
                $rows[] = [
                    'invoiceDate' => $invoice->invoice_date?->format('Y-m-d'),
                    'prRef' => null,
                    'purchaser' => null,
                    'description' => (string) $line->description,
                    'vendor' => (string) $invoice->supplier_name,
                    'vendorPhone' => $invoice->supplier_phone,
                    'campus' => $line->campus,
                    'division' => $line->division,
                    'department' => $line->department,
                    'qty' => (float) $line->qty,
                    'uom' => $line->uom,
                    'unitPriceUsd' => Currency::toUsd((float) $line->unit_price, $currency, $rate),
                    'discountUsd' => Currency::toUsd((float) $line->discount, $currency, $rate),
                    'vatUsd' => $shares[$index] ?? 0.0,
                ];
            }
        }

        return $rows;
    }

    /**
     * Split an invoice's USD VAT across its lines in proportion to each
     * line's net total; the last line takes the rounding remainder so the
     * shares always add back up to the header amount. A zero subtotal has
     * no meaningful base, so the VAT is not attributed at all.
     *
     * @return array<int, float>
     */
    private function allocateVat(Invoice $invoice, float $vatUsd): array
    {
        $lines = $invoice->lines->values();
        $count = $lines->count();
        $base = round((float) $lines->sum('line_total'), 2);

        if ($count === 0 || $base <= 0 || $vatUsd <= 0) {
            return array_fill(0, $count, 0.0);
        }

        $shares = [];
        $allocated = 0.0;

        foreach ($lines as $index => $line) {
            if ($index === $count - 1) {
                $shares[$index] = round($vatUsd - $allocated, 2);

                continue;
            }

            $share = round($vatUsd * (float) $line->line_total / $base, 2);
            $allocated = round($allocated + $share, 2);
            $shares[$index] = $share;
        }

        return $shares;
    }

    /**
     * The referenced cash request must exist and be a petty cash request:
     * a clearance settles the PC branch only (advances are liquidated, cash
     * requests settle themselves). The approved-source and one-clearance
     * guards live in ensureApprovedSource()/ensureNoOtherClearance() and run
     * on create and update alike.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveSource(array $data): CashRequest
    {
        $source = CashRequest::query()->find($data['cash_request_id']);

        if ($source === null) {
            throw ValidationException::withMessages([
                'cash_request_id' => 'Record not found.',
            ]);
        }

        if ($source->type !== CashRequest::TYPE_PETTY_CASH) {
            throw ValidationException::withMessages([
                'cash_request_id' => "Only a petty cash request can be cleared, this one is '{$source->type}'.",
            ]);
        }

        return $source;
    }

    private function ensureApprovedSource(CashRequest $source): void
    {
        if ($source->status !== CashRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'cash_request_id' => "Only an approved cash request can be cleared, this one is '{$source->status}'.",
            ]);
        }
    }

    /**
     * No DB unique index on purpose: a soft-deleted clearance must not block
     * recreating the final one, so the rule lives here only. `$except` keeps
     * an updating clearance from colliding with itself.
     */
    private function ensureNoOtherClearance(CashRequest $source, ?CashClearance $except): void
    {
        $alreadyCleared = CashClearance::query()
            ->where('cash_request_id', $source->getKey())
            ->whereNull('deleted_at')
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($alreadyCleared) {
            throw ValidationException::withMessages([
                'cash_request_id' => 'This cash request already has a clearance.',
            ]);
        }
    }

    /**
     * Rewrite the line list and recompute every derived money column: each
     * line is qty × unit price − discount + VAT, the header total is their
     * sum, and the balance is what remains of the requested amount.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncLines(CashClearance $cashClearance, array $data, User $user): void
    {
        $totalUsd = 0.0;

        foreach (array_values($data['lines']) as $position => $line) {
            $gross = round((float) $line['qty'] * (float) $line['unit_price_usd'], 2);
            $discount = round((float) ($line['discount_usd'] ?? 0), 2);
            $vat = round((float) ($line['vat_usd'] ?? 0), 2);

            // VAT is included in the amount: the line total stays
            // qty × unit price − discount + vat.
            $amountUsd = round($gross - $discount + $vat, 2);
            $totalUsd += $amountUsd;

            CashClearanceLine::create([
                'entity_id' => $cashClearance->entity_id,
                'cash_clearance_id' => $cashClearance->getKey(),
                'position' => $position,
                'invoice_date' => $line['invoice_date'],
                'pr_ref' => $line['pr_ref'] ?? null,
                'purchaser' => $line['purchaser'] ?? null,
                'description' => $line['description'],
                'vendor' => $line['vendor'],
                'vendor_phone' => $line['vendor_phone'] ?? null,
                'campus' => $line['campus'],
                'division' => $line['division'],
                'department' => $line['department'],
                'qty' => $line['qty'],
                'uom' => $line['uom'],
                'unit_price_usd' => $line['unit_price_usd'],
                'discount_usd' => $discount,
                'vat_usd' => $vat,
                'amount_usd' => $amountUsd,
            ]);
        }

        $totalUsd = round($totalUsd, 2);

        $this->ensureWithinRequestAmount($cashClearance, $totalUsd);

        $cashClearance->update([
            'total_usd' => $totalUsd,
            'balance_usd' => round((float) $cashClearance->request_amount_usd - $totalUsd, 2),
            'updated_by' => $user->getKey(),
        ]);
    }

    /**
     * The spend cap: a clearance may not spend more than the request holds.
     * Runs inside the write transaction, so a violation rolls the whole
     * write back (the lines written so far go with it).
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureWithinRequestAmount(CashClearance $cashClearance, float $totalUsd): void
    {
        $requestAmountUsd = (float) $cashClearance->request_amount_usd;

        if (round($totalUsd, 2) > $requestAmountUsd) {
            throw ValidationException::withMessages([
                'lines' => sprintf(
                    'The clearance total (%s USD) exceeds cash request %s: approved %s USD, over by %s USD.',
                    number_format($totalUsd, 2, '.', ''),
                    $cashClearance->cashRequest?->code ?? 'the linked request',
                    number_format($requestAmountUsd, 2, '.', ''),
                    number_format($totalUsd - $requestAmountUsd, 2, '.', ''),
                ),
            ]);
        }
    }
}
