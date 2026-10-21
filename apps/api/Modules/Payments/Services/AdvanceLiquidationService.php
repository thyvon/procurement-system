<?php

namespace Modules\Payments\Services;

use App\Models\User;
use App\Support\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payments\Models\AdvanceLiquidation;
use Modules\Payments\Models\AdvanceLiquidationLine;
use Modules\Payments\Models\CashRequest;
use Modules\Products\Services\CodeGenerationService;

/**
 * Owns every write on an advance liquidation: the approved-source guard, the
 * server-generated LAD code, the line list and the derived money columns
 * (actual expense, cash advance, remaining cash). The client may only suggest
 * lines and header inputs; totals are always recomputed here so the stored
 * document is internally consistent.
 */
class AdvanceLiquidationService
{
    /**
     * Allowed status moves. `cancelled` is terminal, and only `draft` /
     * `returned` documents can still change — the approval engine owns every
     * other transition.
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        AdvanceLiquidation::STATUS_DRAFT => [AdvanceLiquidation::STATUS_CANCELLED],
        AdvanceLiquidation::STATUS_CANCELLED => [],
    ];

    public function __construct(private readonly CodeGenerationService $codes) {}

    /**
     * @param  array<string, mixed>  $data  from advanceLiquidationData()
     */
    public function create(array $data, User $user): AdvanceLiquidation
    {
        return DB::transaction(function () use ($data, $user): AdvanceLiquidation {
            $source = $this->resolveSource($data);

            $this->ensureApprovedSource($source);

            $advanceLiquidation = AdvanceLiquidation::create([
                'entity_id' => $user->entity_id,
                'code' => $this->codes->nextWithCampus(
                    'LAD',
                    AdvanceLiquidation::class,
                    $user->entity_id,
                    $data['campus'],
                    Carbon::parse($data['liquidation_date']),
                ),
                'status' => AdvanceLiquidation::STATUS_DRAFT,
                'cash_request_id' => $source->getKey(),
                'liquidation_date' => $data['liquidation_date'],
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'campus' => $data['campus'],
                'division' => $data['division'],
                'department' => $data['department'],
                'total_actual_expense_usd' => 0,
                'total_cash_advance_usd' => 0,
                'remaining_cash_usd' => 0,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            $this->syncLines($advanceLiquidation, $data, $user);

            return $advanceLiquidation->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  from advanceLiquidationData()
     */
    public function update(AdvanceLiquidation $advanceLiquidation, array $data, User $user): AdvanceLiquidation
    {
        $this->ensureEditable($advanceLiquidation);

        return DB::transaction(function () use ($advanceLiquidation, $data, $user): AdvanceLiquidation {
            $source = $this->resolveSource($data);

            // Updates repoint the liquidation like a create does, so the
            // approved-source guard runs again.
            $this->ensureApprovedSource($source);

            $advanceLiquidation->update([
                'cash_request_id' => $source->getKey(),
                'liquidation_date' => $data['liquidation_date'],
                'exchange_rate' => $data['exchange_rate'] ?? $advanceLiquidation->exchange_rate,
                'campus' => $data['campus'],
                'division' => $data['division'],
                'department' => $data['department'],
                'updated_by' => $user->getKey(),
            ]);

            // Wholesale replace: forceDelete so re-entered lines never collide
            // with soft-deleted rows on a future unique index.
            $advanceLiquidation->lines()->forceDelete();

            $this->syncLines($advanceLiquidation, $data, $user);

            return $advanceLiquidation->refresh();
        });
    }

    public function changeStatus(AdvanceLiquidation $advanceLiquidation, string $status, User $user): AdvanceLiquidation
    {
        $allowed = self::TRANSITIONS[$advanceLiquidation->status] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "An advance liquidation with status '{$advanceLiquidation->status}' cannot move to '{$status}'.",
            ]);
        }

        $advanceLiquidation->update([
            'status' => $status,
            'updated_by' => $user->getKey(),
        ]);

        return $advanceLiquidation->refresh();
    }

    /**
     * Only drafts and returned documents are editable: once a liquidation is
     * in review it belongs to the approval flow, and a decided or cancelled
     * one is closed.
     */
    public function ensureEditable(AdvanceLiquidation $advanceLiquidation): void
    {
        if (! in_array($advanceLiquidation->status, [AdvanceLiquidation::STATUS_DRAFT, AdvanceLiquidation::STATUS_RETURNED], true)) {
            throw ValidationException::withMessages([
                'status' => "An advance liquidation with status '{$advanceLiquidation->status}' cannot be modified.",
            ]);
        }
    }

    public function ensureDeletable(AdvanceLiquidation $advanceLiquidation): void
    {
        if ($advanceLiquidation->status !== AdvanceLiquidation::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => "Only a draft advance liquidation can be deleted, this one is '{$advanceLiquidation->status}'.",
            ]);
        }
    }

    /**
     * The referenced cash request must exist and be an advance request: a
     * liquidation settles the AD branch only (petty cash is cleared, cash
     * requests settle themselves). The approved-source guard lives in
     * ensureApprovedSource() and runs on create and update alike.
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

        if ($source->type !== CashRequest::TYPE_ADVANCE) {
            throw ValidationException::withMessages([
                'cash_request_id' => "Only an advance request can be liquidated, this one is '{$source->type}'.",
            ]);
        }

        return $source;
    }

    private function ensureApprovedSource(CashRequest $source): void
    {
        if ($source->status !== CashRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'cash_request_id' => "Only an approved cash request can be liquidated, this one is '{$source->status}'.",
            ]);
        }
    }

    /**
     * Rewrite the line list and recompute every derived money column. Each
     * line amount converts to USD through its own currency and the header's
     * entry-time exchange rate; the cash advance is copied from the source
     * request and the remainder is what is left of it.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncLines(AdvanceLiquidation $advanceLiquidation, array $data, User $user): void
    {
        $headerExchangeRate = (float) ($advanceLiquidation->exchange_rate ?? 1);
        $totalActualExpenseUsd = 0.0;

        foreach (array_values($data['lines']) as $position => $line) {
            $amountUsd = Currency::toUsd(
                (float) $line['amount'],
                (string) ($line['currency'] ?? Currency::USD),
                $headerExchangeRate,
            );

            $totalActualExpenseUsd += $amountUsd;

            AdvanceLiquidationLine::create([
                'entity_id' => $advanceLiquidation->entity_id,
                'advance_liquidation_id' => $advanceLiquidation->getKey(),
                'position' => $position,
                'description' => $line['description'],
                'currency' => $line['currency'] ?? Currency::USD,
                'amount' => $line['amount'],
                'amount_usd' => $amountUsd,
            ]);
        }

        $source = CashRequest::query()->find($advanceLiquidation->cash_request_id);
        $totalActualExpenseUsd = round($totalActualExpenseUsd, 2);
        $totalCashAdvanceUsd = round((float) $source?->total_usd, 2);

        $advanceLiquidation->update([
            'total_actual_expense_usd' => $totalActualExpenseUsd,
            'total_cash_advance_usd' => $totalCashAdvanceUsd,
            'remaining_cash_usd' => round($totalCashAdvanceUsd - $totalActualExpenseUsd, 2),
            'updated_by' => $user->getKey(),
        ]);
    }
}
