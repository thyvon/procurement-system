<?php

namespace Modules\Approvals\Policies;

use App\Models\User;
use Modules\Approvals\Models\TocaEntry;

/**
 * Table of commitment authority rows: `approvals.view` reads who may act
 * for which amount, `approvals.manage` writes the rows.
 *
 * Model parameters stay nullable so the same abilities serve both instance
 * and class-string checks.
 */
class TocaEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('approvals.view', 'sanctum');
    }

    public function view(User $user, ?TocaEntry $entry = null): bool
    {
        return $user->can('approvals.view', 'sanctum');
    }

    public function create(User $user): bool
    {
        return $user->can('approvals.manage', 'sanctum');
    }

    public function update(User $user, ?TocaEntry $entry = null): bool
    {
        return $user->can('approvals.manage', 'sanctum');
    }

    public function delete(User $user, ?TocaEntry $entry = null): bool
    {
        return $user->can('approvals.manage', 'sanctum');
    }
}
