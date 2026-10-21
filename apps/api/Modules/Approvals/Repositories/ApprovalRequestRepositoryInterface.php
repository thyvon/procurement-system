<?php

namespace Modules\Approvals\Repositories;

use App\Support\Repository\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Approvals\Models\ApprovalRequest;

interface ApprovalRequestRepositoryInterface extends RepositoryInterface
{
    /**
     * Filtered, offset-paginated listing scoped to the caller's entity.
     * Every tray lists each document once — on its newest round — and never
     * lists rounds whose document was deleted. Scoped to a single document
     * (`subject_id`) it becomes that document's full round-by-round history.
     *
     * @param  array{status?: string, subject_type?: string, subject_id?: string, search?: string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function filtered(array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator;

    /**
     * Document tray: actionable work only — requests waiting on the given
     * user's decision plus their own returned documents, one row per
     * document (its newest round) so it always matches `inboxCount()`.
     * Deleted documents never appear.
     *
     * @param  array{status?: string, subject_type?: string, subject_id?: string, search?: string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function inbox(int $userId, array $filters = [], int $perPage = 20, int $page = 1): LengthAwarePaginator;

    /**
     * Document tray: requests submitted by the given user — one row per
     * document (its newest round), deleted documents never listed.
     *
     * @param  array{status?: string, subject_type?: string, subject_id?: string, search?: string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function outbox(int $userId, array $filters = [], int $perPage = 20, int $page = 1): LengthAwarePaginator;

    /**
     * Open work for the given user: decisions they owe plus their own returned
     * documents. Drives the sidebar badge.
     */
    public function inboxCount(int $userId): int;

    /**
     * The newest round ever submitted for one document (or null when it never
     * entered review). Actions eager-loaded with their actors — the print
     * sheets derive the signature block from exactly this round.
     */
    public function latestForSubject(string $subjectType, string $subjectId): ?ApprovalRequest;
}
