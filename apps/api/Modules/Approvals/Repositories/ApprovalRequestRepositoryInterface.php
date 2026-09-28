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
     * Document tray: actionable work only — requests waiting on the given
     * user's decision plus their own returned documents. Decided history lives
     * in the outbox and the full request list, so this always matches
     * `inboxCount()`.
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

    /**
     * Open work for the given user: decisions they owe plus their own returned
     * documents. Drives the sidebar badge.
     */
    public function inboxCount(int $userId): int;
}
