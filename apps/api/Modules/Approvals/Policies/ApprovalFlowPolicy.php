<?php

namespace Modules\Approvals\Policies;

use App\Models\User;
use Modules\Approvals\Models\ApprovalFlow;

/**
 * Approval configuration abilities follow the repo-wide permission
 * vocabulary: `approvals.view` reads the configuration screens,
 * `approvals.manage` writes them (flows, their steps and amount bands).
 *
 * Model parameters stay nullable so the same abilities serve both instance
 * (`update($user, $flow)`) and class-string (`update($user, ApprovalFlow::class)`)
 * checks.
 */
class ApprovalFlowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('approvals.view', 'sanctum');
    }

    public function view(User $user, ?ApprovalFlow $flow = null): bool
    {
        return $user->can('approvals.view', 'sanctum');
    }

    public function create(User $user): bool
    {
        return $user->can('approvals.manage', 'sanctum');
    }

    public function update(User $user, ?ApprovalFlow $flow = null): bool
    {
        return $user->can('approvals.manage', 'sanctum');
    }

    public function delete(User $user, ?ApprovalFlow $flow = null): bool
    {
        return $user->can('approvals.manage', 'sanctum');
    }
}
