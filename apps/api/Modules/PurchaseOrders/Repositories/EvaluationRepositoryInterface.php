<?php

namespace Modules\PurchaseOrders\Repositories;

use App\Support\Repository\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EvaluationRepositoryInterface extends RepositoryInterface
{
    /**
     * Filtered, offset-paginated listing scoped to the caller's entity.
     *
     * @param  array{search?: string}  $filters
     */
    public function filtered(array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator;
}
