<?php

namespace Modules\Payments\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

            if ($source->status !== CashRequest::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'cash_request_id' => "Only an approved cash request can be cleared, this one is '{$source->status}'.",
                ]);
            }

            // No DB unique index on purpose: a soft-deleted clearance must not
            // block recreating the final one, so the rule lives here only.
            $alreadyCleared = CashClearance::query()
                ->where('cash_request_id', $source->getKey())
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadyCleared) {
                throw ValidationException::withMessages([
                    'cash_request_id' => 'This cash request already has a clearance.',
                ]);
            }

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
     * The referenced cash request must exist and be a petty cash request:
     * a clearance settles the PC branch only (advances are liquidated, cash
     * requests settle themselves). The approved-source and one-clearance
     * guards belong to create(): they are the entry gate, while updates only
     * keep the money columns in step with the source.
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

        $cashClearance->update([
            'total_usd' => $totalUsd,
            'balance_usd' => round((float) $cashClearance->request_amount_usd - $totalUsd, 2),
            'updated_by' => $user->getKey(),
        ]);
    }
}
