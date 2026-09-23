<?php

namespace Modules\Users\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view', 'sanctum');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can('users.view', 'sanctum')
            && ($actor->entity_id === null
                || $target->entity_id === null
                || $actor->entity_id === $target->entity_id);
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage', 'sanctum');
    }

    public function update(User $actor, User $target): bool
    {
        if ($actor->getKey() === $target->getKey()) {
            return true;
        }

        return $actor->can('users.manage', 'sanctum')
            && ($actor->entity_id === null || $actor->entity_id === $target->entity_id);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('users.manage', 'sanctum')
            && $actor->getKey() !== $target->getKey()
            && ($actor->entity_id === null || $actor->entity_id === $target->entity_id);
    }
}
