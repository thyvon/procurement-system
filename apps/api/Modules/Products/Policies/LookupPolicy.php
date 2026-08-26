<?php

namespace Modules\Products\Policies;

use App\Models\User;

/**
 * Shared authorization shape for product-module lookup entities:
 * every authenticated user reads; admins manage.
 */
abstract class LookupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, mixed $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, mixed $model): bool
    {
        return $user->hasRole('admin');
    }
}
