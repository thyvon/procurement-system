<?php

namespace Modules\Organization\Repositories;

use App\Support\Repository\RepositoryInterface;
use Modules\Organization\Models\Entity;

interface EntityRepositoryInterface extends RepositoryInterface
{
    public function findByCode(string $code): ?Entity;
}
