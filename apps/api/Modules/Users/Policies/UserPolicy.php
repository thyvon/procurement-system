<?php

namespace Modules\Users\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->entity_id === null
            || $target->entity_id === null
            || $actor->entity_id === $target->entity_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $actor, User $target): bool
    {
        if ($actor->getKey() === $target->getKey()) {
            return true;
        }

        return $actor->hasRole('admin')
            && ($actor->entity_id === null || $actor->entity_id === $target->entity_id);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->hasRole('admin')
            && $actor->getKey() !== $target->getKey()
            && ($actor->entity_id === null || $actor->entity_id === $target->entity_id);
    }
}
