<?php

namespace Modules\Approvals\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Services\ApprovalSubjectRegistry;

class ApprovalRequestRepository extends BaseRepository implements ApprovalRequestRepositoryInterface
{
    public function __construct(
        ApprovalRequest $model,
        private readonly ApprovalSubjectRegistry $subjects,
    ) {
        parent::__construct($model);
    }

    /**
     * @param  array{status?: string, subject_type?: string, subject_id?: string, search?: string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function filtered(array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = $this->query()->with(['creator', 'actions.actor']);

        $this->whereSubjectStillExists($query);

        // The trays are document lists: each document shows once, on its
        // newest round. Scoped to a single document (subject_id) the same
        // endpoint is that document's round-by-round history, which stays
        // complete so return/resubmit cycles remain auditable.
        if (empty($filters['subject_id'])) {
            $this->latestRoundPerDocument($query);
        }

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * The tray is actionable work only: decisions the user owes plus the
     * documents returned to them. Only the newest round of each document is
     * listed, so a document returned and resubmitted several times appears
     * once — the same shape as the outbox and the full list, and always in
     * step with `inboxCount()`.
     *
     * @param  array{status?: string, subject_type?: string, subject_id?: string, search?: string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function inbox(int $userId, array $filters = [], int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = $this->inboxQuery($userId)->with(['creator']);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function outbox(int $userId, array $filters = [], int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['creator'])
            ->where('created_by', $userId);

        $this->whereSubjectStillExists($query);
        $this->latestRoundPerDocument($query);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function inboxCount(int $userId): int
    {
        return $this->inboxQuery($userId)->count();
    }

    public function latestForSubject(string $subjectType, string $subjectId): ?ApprovalRequest
    {
        return $this->query()
            ->with(['actions.actor'])
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Actionable work for the user, limited to the latest round per document:
     * older returned rounds are superseded once the document is resubmitted.
     *
     * @return Builder<ApprovalRequest>
     */
    private function inboxQuery(int $userId): Builder
    {
        $query = $this->query()
            ->where(function (Builder $q) use ($userId): void {
                $q->where(function (Builder $pending) use ($userId): void {
                    $pending->where('status', ApprovalRequest::STATUS_PENDING)
                        ->where('current_assignee_id', $userId);
                })->orWhere(function (Builder $returned) use ($userId): void {
                    $returned->where('status', ApprovalRequest::STATUS_RETURNED)
                        ->where('created_by', $userId);
                });
            });

        $this->whereSubjectStillExists($query);
        $this->latestRoundPerDocument($query);

        return $query;
    }

    /**
     * An approval only matters while its document is alive: rounds whose
     * subject was (soft-)deleted drop out of every tray and badge count,
     * while the rows stay in the table and reappear if the document is
     * restored. One existence probe per subject type — several types can
     * share a table (petty cash / advance / cash → cash_requests), so the
     * outer `subject_type` picks the right one.
     *
     * @param  Builder<ApprovalRequest>  $query
     */
    private function whereSubjectStillExists(Builder $query): void
    {
        $query->where(function (Builder $tray): void {
            foreach (ApprovalSubjectRegistry::types() as $subjectType) {
                $model = $this->subjects->definition($subjectType)['model'];

                $tray->orWhere(function (Builder $round) use ($subjectType, $model): void {
                    $table = (new $model)->getTable();

                    $round->where('subject_type', $subjectType)
                        ->whereExists(function (QueryBuilder $exists) use ($model, $table): void {
                            $exists->selectRaw('1')
                                ->from($table)
                                ->whereColumn("{$table}.id", 'approval_requests.subject_id');

                            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                                $exists->whereNull("{$table}.deleted_at");
                            }
                        });
                });
            }
        });
    }

    /**
     * One row per document — its newest round. ULIDs are time-ordered, so
     * MAX(id) is the latest submission; resubmitting a returned document
     * must not multiply it across the trays. Applied after the user's own
     * scope and before the filters, so status/search/date filters act on the
     * current round of a document, never on superseded ones.
     *
     * @param  Builder<ApprovalRequest>  $query
     */
    private function latestRoundPerDocument(Builder $query): void
    {
        $query->whereIn(
            'id',
            $this->query()
                ->selectRaw('MAX(id)')
                ->groupBy('subject_type', 'subject_id')
        );
    }

    /**
     * @param  array{status?: string, subject_type?: string, subject_id?: string, search?: string, date_from?: ?string, date_to?: ?string}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('snapshot->documentCode', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }
}
