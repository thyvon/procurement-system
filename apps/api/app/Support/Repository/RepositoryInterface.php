<?php

namespace App\Support\Repository;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function find(string $id): ?Model;

    /**
     * @return array<int, Model>
     */
    public function all(): array;

    /**
     * @return CursorPaginator<int, Model>
     */
    public function paginate(int $perPage = 20): CursorPaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;
}
