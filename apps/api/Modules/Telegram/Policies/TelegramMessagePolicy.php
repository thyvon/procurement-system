<?php

namespace Modules\Telegram\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Telegram abilities follow the repo-wide permission vocabulary: holders of
 * `telegram.view` read accounts/contacts/conversations, `telegram.manage`
 * writes them and drives the login flow. Model parameters are nullable so
 * the same policy serves both instance and class-string abilities.
 */
class TelegramMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('telegram.view', 'sanctum');
    }

    public function view(User $user, ?Model $model = null): bool
    {
        return $user->can('telegram.view', 'sanctum');
    }

    public function create(User $user): bool
    {
        return $user->can('telegram.manage', 'sanctum');
    }

    public function update(User $user, ?Model $model = null): bool
    {
        return $user->can('telegram.manage', 'sanctum');
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        return $user->can('telegram.manage', 'sanctum');
    }
}
