<?php

namespace Modules\Payments\Repositories;

use App\Support\Repository\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AdvanceLiquidationRepositoryInterface extends RepositoryInterface
{
    /**
     * Filtered, offset-paginated listing scoped to the caller's entity.
     *
     * @param  array{search?: string, status?: string}  $filters
     */
    public function filtered(array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator;
}
