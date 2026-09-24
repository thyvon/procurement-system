<?php

namespace Modules\Products\Repositories;

use App\Support\Repository\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Products\Models\Product;

interface ProductRepositoryInterface extends RepositoryInterface
{
    /**
     * Filtered, offset-paginated listing. Search falls back to SQL LIKE
     * when Meilisearch is unreachable (local/dev resilience).
     *
     * @param  array{search?: string, category_id?: string, group_id?: string, brand_id?: string, status?: string, type?: string}  $filters
     */
    public function filtered(array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator;

    /**
     * Full-text search via Meilisearch, scoped to the caller's entity.
     *
     * @return array<int, string> matching product ULIDs
     */
    public function searchIds(string $query, int $limit = 50): array;

    /**
     * Dropdown feed: id + label pairs.
     *
     * @return array<int, Product>
     */
    public function options(): array;
}
