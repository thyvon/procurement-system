<?php

namespace Modules\Approvals\Services;

use App\Models\User;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Approvals\Models\TocaEntry;
use Modules\Payments\Models\AdvanceLiquidation;
use Modules\Payments\Models\AdvanceLiquidationLine;
use Modules\Payments\Models\CashClearance;
use Modules\Payments\Models\CashClearanceLine;
use Modules\Payments\Models\CashRequest;
use Modules\Payments\Models\CashRequestLine;
use Modules\PurchaseOrders\Models\Evaluation;
use Modules\PurchaseOrders\Models\EvaluationItem;
use Modules\PurchaseOrders\Models\EvaluationQuotation;

/**
 * Maps a subject_type (the "module" being approved) to the metadata the
 * generic approval engine needs: which model backs it, where its amount and
 * code live, and which permission guards submission. Adding a module to
 * approvals = one entry here + its approval_setting seed.
 */
class ApprovalSubjectRegistry
{
    /**
     * `currency_column`/`exchange_rate_column` mark subjects recorded in a
     * non-USD currency (Cambodia runs USD + KHR); their amount converts to
     * USD at the registry boundary because approval bands are USD-scale.
     * Subjects without those columns keep their raw amount.
     *
     * `submittable_statuses` freezes a subject once it has been decided:
     * only these statuses may enter (or re-enter) the approval flow, so an
     * approved or rejected document can never open a new round.
     *
     * `type_value` discriminates subjects that share one model across several
     * subject types (petty cash / advance / cash all back onto CashRequest):
     * a document whose `type` differs is rejected instead of being routed
     * through another subject type's flow and authority bands.
     *
     * @var array<string, array{model: class-string<Model>, amount_column: string, code_column: string, permission: string, currency_column?: string, exchange_rate_column?: string, submittable_statuses?: array<int, string>, type_value?: string}>
     */
    private const SUBJECTS = [
        'evaluation' => [
            'model' => Evaluation::class,
            'amount_column' => 'awarded_total',
            'code_column' => 'code',
            'permission' => 'evaluations.manage',
            'currency_column' => 'currency',
            'exchange_rate_column' => 'exchange_rate',
            'submittable_statuses' => ['draft', 'returned'],
        ],
        'petty_cash_request' => [
            'model' => CashRequest::class,
            'type_value' => CashRequest::TYPE_PETTY_CASH,
            'amount_column' => 'total_usd',
            'code_column' => 'code',
            'permission' => 'payments.manage',
            'exchange_rate_column' => 'exchange_rate',
            'submittable_statuses' => ['draft', 'returned'],
        ],
        'advance_request' => [
            'model' => CashRequest::class,
            'type_value' => CashRequest::TYPE_ADVANCE,
            'amount_column' => 'total_usd',
            'code_column' => 'code',
            'permission' => 'payments.manage',
            'exchange_rate_column' => 'exchange_rate',
            'submittable_statuses' => ['draft', 'returned'],
        ],
        'cash_request' => [
            'model' => CashRequest::class,
            'type_value' => CashRequest::TYPE_CASH,
            'amount_column' => 'total_usd',
            'code_column' => 'code',
            'permission' => 'payments.manage',
            'exchange_rate_column' => 'exchange_rate',
            'submittable_statuses' => ['draft', 'returned'],
        ],
        'cash_clearance' => [
            'model' => CashClearance::class,
            'amount_column' => 'total_usd',
            'code_column' => 'code',
            'permission' => 'payments.manage',
            'exchange_rate_column' => 'exchange_rate',
            'submittable_statuses' => ['draft', 'returned'],
        ],
        'advance_liquidation' => [
            'model' => AdvanceLiquidation::class,
            'amount_column' => 'total_actual_expense_usd',
            'code_column' => 'code',
            'permission' => 'payments.manage',
            'exchange_rate_column' => 'exchange_rate',
            'submittable_statuses' => ['draft', 'returned'],
        ],
    ];

    /**
     * @return array{model: class-string<Model>, amount_column: string, code_column: string, permission: string, currency_column?: string, exchange_rate_column?: string, submittable_statuses?: array<int, string>, type_value?: string}
     */
    public function definition(string $subjectType): array
    {
        $definition = self::SUBJECTS[$subjectType] ?? null;

        if ($definition === null) {
            throw ValidationException::withMessages([
                'subjectType' => "Unknown approval subject type '{$subjectType}'.",
            ]);
        }

        return $definition;
    }

    public function find(string $subjectType, string $subjectId): Model
    {
        $definition = $this->definition($subjectType);

        $subject = $definition['model']::query()->find($subjectId);

        if ($subject === null) {
            throw ValidationException::withMessages([
                'subjectId' => 'Record not found.',
            ]);
        }

        $typeValue = $definition['type_value'] ?? null;

        if ($typeValue !== null && (string) $subject->getAttribute('type') !== $typeValue) {
            throw ValidationException::withMessages([
                'subjectType' => 'The document does not match this approval subject type.',
            ]);
        }

        return $subject;
    }

    /**
     * A subject may only enter the approval flow from an editable status —
     * decided documents (approved/rejected) are frozen so a finished round
     * can never be reopened.
     */
    public function ensureSubmittable(string $subjectType, Model $subject): void
    {
        $statuses = $this->definition($subjectType)['submittable_statuses'] ?? null;

        if ($statuses === null) {
            return;
        }

        $status = (string) $subject->getAttribute('status');

        if (! in_array($status, $statuses, true)) {
            throw ValidationException::withMessages([
                'status' => "A document with status '{$status}' cannot be submitted for approval.",
            ]);
        }
    }

    public function amount(string $subjectType, Model $subject): float
    {
        $definition = $this->definition($subjectType);

        $amount = (float) $subject->getAttribute($definition['amount_column']);

        $currencyColumn = $definition['currency_column'] ?? null;
        if ($currencyColumn === null) {
            return $amount;
        }

        return Currency::toUsd(
            $amount,
            (string) $subject->getAttribute($currencyColumn),
            (float) $subject->getAttribute($definition['exchange_rate_column']),
        );
    }

    public function code(string $subjectType, Model $subject): ?string
    {
        $definition = $this->definition($subjectType);

        $code = $subject->getAttribute($definition['code_column']);

        return $code === null ? null : (string) $code;
    }

    /**
     * The document fields a round freezes at submit time. Every later round
     * snapshots its own copy, so approvers can see exactly what the owner
     * changed after a return instead of re-reading the whole document.
     *
     * @return array<string, mixed>
     */
    public function documentSnapshot(string $subjectType, Model $subject): array
    {
        return match ($subjectType) {
            'evaluation' => $this->evaluationSnapshot($subject),
            'petty_cash_request', 'advance_request', 'cash_request' => $this->cashRequestSnapshot($subject),
            'cash_clearance' => $this->cashClearanceSnapshot($subject),
            'advance_liquidation' => $this->advanceLiquidationSnapshot($subject),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluationSnapshot(Model $subject): array
    {
        /** @var Evaluation $subject */
        $items = $subject->items()
            ->orderBy('position')
            ->get()
            ->map(fn (EvaluationItem $item): string => sprintf(
                '%s × %s %s',
                $item->item_code,
                self::trimNumber((float) $item->qty),
                (string) $item->uom,
            ))
            ->values()
            ->all();

        $selectedSuppliers = $subject->quotations()
            ->with('lines')
            ->get()
            ->filter(fn (EvaluationQuotation $quotation): bool => $quotation->lines->contains('is_selected', true))
            ->pluck('supplier_name')
            ->values()
            ->all();

        return [
            'awardedTotal' => number_format((float) $subject->getAttribute('awarded_total'), 2, '.', ''),
            'currency' => (string) $subject->getAttribute('currency'),
            'recommendationBasis' => (string) $subject->getAttribute('recommendation_basis'),
            'items' => $items,
            'selectedSuppliers' => $selectedSuppliers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cashRequestSnapshot(Model $subject): array
    {
        /** @var CashRequest $subject */
        return [
            'code' => (string) $subject->getAttribute('code'),
            'type' => (string) $subject->getAttribute('type'),
            'status' => (string) $subject->getAttribute('status'),
            'campus' => (string) $subject->getAttribute('campus'),
            'division' => (string) $subject->getAttribute('division'),
            'department' => (string) $subject->getAttribute('department'),
            'requestDate' => $subject->getAttribute('request_date')?->format('Y-m-d'),
            'totalUsd' => number_format((float) $subject->getAttribute('total_usd'), 2, '.', ''),
            'lines' => $this->amountLineSnapshot($subject),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cashClearanceSnapshot(Model $subject): array
    {
        /** @var CashClearance $subject */
        $lines = $subject->lines()
            ->orderBy('position')
            ->get()
            ->map(fn (CashClearanceLine $line): array => [
                'invoiceDate' => $line->invoice_date?->format('Y-m-d'),
                'description' => (string) $line->description,
                'vendor' => (string) $line->vendor,
                'campus' => (string) $line->campus,
                'division' => (string) $line->division,
                'department' => (string) $line->department,
                'qty' => number_format((float) $line->qty, 2, '.', ''),
                'unitPriceUsd' => number_format((float) $line->unit_price_usd, 2, '.', ''),
                'amountUsd' => number_format((float) $line->amount_usd, 2, '.', ''),
            ])
            ->values()
            ->all();

        return [
            'code' => (string) $subject->getAttribute('code'),
            'status' => (string) $subject->getAttribute('status'),
            'clearanceDate' => $subject->getAttribute('clearance_date')?->format('Y-m-d'),
            'totalUsd' => number_format((float) $subject->getAttribute('total_usd'), 2, '.', ''),
            'requestAmountUsd' => number_format((float) $subject->getAttribute('request_amount_usd'), 2, '.', ''),
            'balanceUsd' => number_format((float) $subject->getAttribute('balance_usd'), 2, '.', ''),
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function advanceLiquidationSnapshot(Model $subject): array
    {
        /** @var AdvanceLiquidation $subject */
        return [
            'code' => (string) $subject->getAttribute('code'),
            'status' => (string) $subject->getAttribute('status'),
            'liquidationDate' => $subject->getAttribute('liquidation_date')?->format('Y-m-d'),
            'campus' => (string) $subject->getAttribute('campus'),
            'division' => (string) $subject->getAttribute('division'),
            'department' => (string) $subject->getAttribute('department'),
            'totalActualExpenseUsd' => number_format((float) $subject->getAttribute('total_actual_expense_usd'), 2, '.', ''),
            'totalCashAdvanceUsd' => number_format((float) $subject->getAttribute('total_cash_advance_usd'), 2, '.', ''),
            'remainingCashUsd' => number_format((float) $subject->getAttribute('remaining_cash_usd'), 2, '.', ''),
            'lines' => $this->amountLineSnapshot($subject),
        ];
    }

    /**
     * Shared line shape for cash requests and advance liquidations: the
     * description, its currency, the entered amount and the USD equivalent.
     *
     * @return array<int, array{description: string, currency: string, amount: string, amountUsd: string}>
     */
    private function amountLineSnapshot(Model $subject): array
    {
        return $subject->lines()
            ->orderBy('position')
            ->get()
            ->map(fn (CashRequestLine|AdvanceLiquidationLine $line): array => [
                'description' => (string) $line->description,
                'currency' => (string) $line->currency,
                'amount' => number_format((float) $line->amount, 2, '.', ''),
                'amountUsd' => number_format((float) $line->amount_usd, 2, '.', ''),
            ])
            ->values()
            ->all();
    }

    /**
     * `decimal:4` columns arrive as "10.0000"; human diffs read better.
     */
    private static function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }

    public static function permission(string $subjectType): ?string
    {
        return self::SUBJECTS[$subjectType]['permission'] ?? null;
    }

    /**
     * Subject types the approval engine understands — the vocabulary the
     * configuration screens offer (settings, flows, TOCA authority rows).
     *
     * @return array<int, string>
     */
    public static function types(): array
    {
        return array_keys(self::SUBJECTS);
    }

    /**
     * Users whose authority entries cover the given amount for this subject
     * type.
     *
     * When `$stepKey` is given, entries scoped to another step are excluded —
     * entries without a step scope still qualify for every step.
     *
     * @return Collection<int, User>
     */
    public function candidates(string $subjectType, float $amount, ?string $stepKey = null): Collection
    {
        $userIds = TocaEntry::query()
            ->with('users:id')
            ->where('subject_type', $subjectType)
            ->get()
            ->filter(fn (TocaEntry $entry): bool => $entry->coversAmount($amount, $stepKey))
            ->flatMap(fn (TocaEntry $entry) => $entry->users->pluck('id'))
            ->unique();

        return User::query()
            ->whereIn('id', $userIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function canAct(string $subjectType, int $userId, float $amount, ?string $stepKey = null): bool
    {
        return TocaEntry::query()
            ->whereHas('users', fn ($query) => $query->whereKey($userId))
            ->where('subject_type', $subjectType)
            ->get()
            ->contains(fn (TocaEntry $entry): bool => $entry->coversAmount($amount, $stepKey));
    }
}
