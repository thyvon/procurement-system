<?php

namespace Modules\Products\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared authorization shape for product-module lookup entities:
 * every authenticated user reads; admins manage.
 *
 * Model parameters are nullable so the same policies serve both
 * instance abilities (`update($user, $model)`) and class-string
 * abilities (`update($user, Model::class)`).
 */
abstract class LookupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ?Model $model = null): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('admin');
    }
}
