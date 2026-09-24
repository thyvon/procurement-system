<?php

namespace Modules\PurchaseOrders\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evaluation abilities follow the repo-wide permission vocabulary:
 * holders of `evaluations.view` read; holders of `evaluations.manage` write.
 * Model parameters are nullable so the same policy serves both instance
 * abilities (`update($user, $model)`) and class-string abilities
 * (`update($user, Model::class)`).
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
        return $user->can('evaluations.manage', 'sanctum');
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        return $user->can('evaluations.manage', 'sanctum');
    }
}
