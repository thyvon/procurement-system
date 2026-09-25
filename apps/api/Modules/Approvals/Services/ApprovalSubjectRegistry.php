<?php

namespace Modules\Approvals\Services;

use App\Models\User;
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
     * @var array<string, array{model: class-string<Model>, amount_column: string, code_column: string, permission: string}>
     */
    private const SUBJECTS = [
        'evaluation' => [
            'model' => Evaluation::class,
            'amount_column' => 'awarded_total',
            'code_column' => 'code',
            'permission' => 'evaluations.manage',
        ],
    ];

    /**
     * @return array{model: class-string<Model>, amount_column: string, code_column: string, permission: string}
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

        return (float) $subject->getAttribute($definition['amount_column']);
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
     * Users whose TOCA rows cover the given amount for this subject type.
     *
     * @return Collection<int, User>
     */
    public function candidates(string $subjectType, float $amount): Collection
    {
        $userIds = TocaEntry::query()
            ->where('subject_type', $subjectType)
            ->get()
            ->filter(fn (TocaEntry $entry): bool => $entry->coversAmount($amount))
            ->pluck('user_id')
            ->unique();

        return User::query()
            ->whereIn('id', $userIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function canAct(string $subjectType, int $userId, float $amount): bool
    {
        return TocaEntry::query()
            ->where('user_id', $userId)
            ->where('subject_type', $subjectType)
            ->get()
            ->contains(fn (TocaEntry $entry): bool => $entry->coversAmount($amount));
    }
}
