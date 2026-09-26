<?php

namespace Modules\Approvals\Services;

use App\Models\User;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Approvals\Models\TocaEntry;
use Modules\PurchaseOrders\Models\Evaluation;

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
     * @var array<string, array{model: class-string<Model>, amount_column: string, code_column: string, permission: string, currency_column?: string, exchange_rate_column?: string}>
     */
    private const SUBJECTS = [
        'evaluation' => [
            'model' => Evaluation::class,
            'amount_column' => 'awarded_total',
            'code_column' => 'code',
            'permission' => 'evaluations.manage',
            'currency_column' => 'currency',
            'exchange_rate_column' => 'exchange_rate',
        ],
    ];

    /**
     * @return array{model: class-string<Model>, amount_column: string, code_column: string, permission: string, currency_column?: string, exchange_rate_column?: string}
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

        return $subject;
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
