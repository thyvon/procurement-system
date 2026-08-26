<?php

namespace Modules\Users\Repositories;

use App\Models\User;
use App\Support\Repository\RepositoryInterface;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?User;
}
