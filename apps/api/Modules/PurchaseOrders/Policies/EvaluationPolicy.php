<?php

namespace Modules\PurchaseOrders\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evaluation abilities follow the repo-wide permission vocabulary:
 * holders of `evaluations.view` read. Edit/delete require BOTH conditions:
 * the `evaluations.manage` permission AND ownership (`created_by` = the
 * actor) — everyone manages only the documents they created.
 * Model parameters are nullable so the same policy serves both instance
 * abilities (`update($user, $model)`) and class-string abilities
 * (`update($user, $model::class)`); without a model there is no owner, so
 * edit/delete deny.
 */
class EvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('evaluations.view', 'sanctum');
    }

    public function view(User $user, ?Model $model = null): bool
    {
        return $user->can('evaluations.view', 'sanctum');
    }

    public function create(User $user): bool
    {
        return $user->can('evaluations.manage', 'sanctum');
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
        return $user->can('evaluations.manage', 'sanctum')
            && $model !== null
            && $model->getAttribute('created_by') !== null
            && (int) $model->getAttribute('created_by') === (int) $user->getKey();
    }
}
