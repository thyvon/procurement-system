<?php

namespace Modules\Approvals\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Approvals\Services\ApprovalSubjectRegistry;

/**
 * Approval abilities follow the repo-wide permission vocabulary:
 * `approvals.view` reads (list/show/inbox/act), `approvals.manage` is the
 * admin-side config ability. Submission additionally requires the subject
 * module's own permission (e.g. `evaluations.manage`) so a read-only user
 * can never push a document into review.
 */
class ApprovalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('approvals.view', 'sanctum');
    }

    public function view(User $user, ?Model $model = null): bool
    {
        return $user->can('approvals.view', 'sanctum');
    }

    public function submit(User $user, ?string $subjectType = null): bool
    {
        if (! $user->can('approvals.view', 'sanctum')) {
            return false;
        }

        $permission = $subjectType === null ? null : ApprovalSubjectRegistry::permission($subjectType);

        return $permission === null || $user->can($permission, 'sanctum');
    }

    public function act(User $user, ?Model $model = null): bool
    {
        return $user->can('approvals.view', 'sanctum');
    }
}
