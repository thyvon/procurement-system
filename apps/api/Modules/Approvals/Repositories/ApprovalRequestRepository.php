<?php

namespace Modules\Approvals\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Approvals\Models\ApprovalRequest;

class ApprovalRequestRepository extends BaseRepository implements ApprovalRequestRepositoryInterface
{
    public function __construct(ApprovalRequest $model)
    {
        parent::__construct($model);
    }

    public function filtered(array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = $this->query()->with(['creator', 'actions.actor']);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * The tray is actionable work only: decisions the user owes plus the
     * documents returned to them. Decided history lives in the outbox and the
     * full request list, so the list always matches `inboxCount()`.
     *
     * @param  array{status?: string, subject_type?: string, subject_id?: string, search?: string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function inbox(int $userId, array $filters = [], int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['creator'])
            ->where(function (Builder $q) use ($userId): void {
                $q->where(function (Builder $assignee) use ($userId): void {
                    $assignee->where('status', ApprovalRequest::STATUS_PENDING)
                        ->where('current_assignee_id', $userId);
                })->orWhere(function (Builder $returned) use ($userId): void {
                    $returned->where('status', ApprovalRequest::STATUS_RETURNED)
                        ->where('created_by', $userId);
                });
            });

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

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function inboxCount(int $userId): int
    {
        return $this->query()
            ->where(function (Builder $q) use ($userId): void {
                $q->where(function (Builder $pending) use ($userId): void {
                    $pending->where('status', ApprovalRequest::STATUS_PENDING)
                        ->where('current_assignee_id', $userId);
                })->orWhere(function (Builder $returned) use ($userId): void {
                    $returned->where('status', ApprovalRequest::STATUS_RETURNED)
                        ->where('created_by', $userId);
                });
            })
            ->count();
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
