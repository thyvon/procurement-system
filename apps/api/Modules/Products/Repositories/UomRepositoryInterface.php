<?php

namespace Modules\Products\Repositories;

use App\Support\Repository\RepositoryInterface;
use Modules\Products\Models\Uom;

interface UomRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<int, Uom>
     */
    public function allWithSubUnits(): array;
}
