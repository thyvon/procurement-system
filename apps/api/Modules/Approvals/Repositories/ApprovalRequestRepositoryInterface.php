<?php

namespace Modules\Approvals\Repositories;

use App\Support\Repository\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ApprovalRequestRepositoryInterface extends RepositoryInterface
{
    /**
     * Filtered, offset-paginated listing scoped to the caller's entity.
     *
     * @param  array{status?: string, subject_type?: string, subject_id?: string, search?: string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function filtered(array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator;

    /**
     * Document tray: requests waiting on the given user's decision, plus the
     * decided requests the given user acted on.
     *
     * @param  array{status?: string, subject_type?: string, subject_id?: string, search?: string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function inbox(int $userId, array $filters = [], int $perPage = 20, int $page = 1): LengthAwarePaginator;

    /**
     * Document tray: requests submitted by the given user.
     *
     * @param  array{status?: string, subject_type?: string, subject_id?: string, search?: string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function outbox(int $userId, array $filters = [], int $perPage = 20, int $page = 1): LengthAwarePaginator;

    public function pendingCount(int $userId): int;
}
