<?php

namespace Modules\Payments\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Cash clearance abilities follow the repo-wide permission vocabulary: holders of
 * `payments.view` read. Edit/delete require BOTH conditions: the
 * `payments.manage` permission AND ownership (`created_by` = the actor) —
 * everyone manages only the documents they created. Model parameters are
 * nullable so the same policy serves both instance abilities
 * (`update($user, $model)`) and class-string abilities
 * (`update($user, $model::class)`); without a model there is no owner, so
 * edit/delete deny.
 */
class CashClearancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view', 'sanctum');
    }

    public function view(User $user, ?Model $model = null): bool
    {
        return $user->can('payments.view', 'sanctum');
    }

    public function create(User $user): bool
    {
        return $user->can('payments.manage', 'sanctum');
    }

    public function update(User $user, ?Model $model = null): bool
    {
        return $this->canWrite($user, $model);
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        return $this->canWrite($user, $model);
    }

    private function canWrite(User $user, ?Model $model): bool
    {
        return $user->can('payments.manage', 'sanctum')
            && $model !== null
            && $model->getAttribute('created_by') !== null
            && (int) $model->getAttribute('created_by') === (int) $user->getKey();
    }
}
