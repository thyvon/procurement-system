<?php

namespace App\Support\Repository;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    public function __construct(protected Model $model) {}

    /**
     * @return Builder<Model>
     */
    protected function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function find(string $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function all(): array
    {
        return $this->query()->get()->all();
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()->cursorPaginate($perPage);
    }

    public function create(array $attributes): Model
    {
        return $this->model->newInstance($attributes)->save()
            ? $this->model->newInstance()
            : throw new \RuntimeException('Failed to persist model.');
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model;
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}
