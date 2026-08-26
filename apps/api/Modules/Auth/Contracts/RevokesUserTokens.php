<?php

namespace Modules\Auth\Contracts;

use App\Models\User;

interface RevokesUserTokens
{
    public function revokeAllFor(User $user): void;
}
