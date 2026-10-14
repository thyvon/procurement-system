<?php

namespace Modules\Organization\Http\Controllers\Concerns;

use App\Models\User;
use App\Support\Http\ApiResponse;
use App\Support\Repository\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Reusable CRUD for organization-module lookup entities (campus, division,
 * department). Mirrors the Products InteractsWithLookups recipe: concrete
 * controllers provide the repository/model/resource triple plus per-request
 * validation; this trait owns authorization + envelope behaviour.
 */
trait InteractsWithOrgLookups
{
    /** @return RepositoryInterface */
    abstract protected function repo();

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** @return class-string<ResourceCollection> */
    abstract protected function resourceClass(): string;

    protected function doIndex(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', $this->modelClass());

        return call_user_func([$this->resourceClass(), 'collection'], $this->repo()->paginate(100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function doStore(array $data): JsonResponse
    {
        $this->authorize('create', $this->modelClass());

        /** @var User $user */
        $user = request()->user();

        $model = $this->repo()->create([
            ...$data,
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        return ApiResponse::success(new ($this->resourceClass())($model), 201);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function doUpdate(Model $model, array $data): mixed
    {
        $this->authorize('update', $model);

        if (in_array('updated_by', $model->getFillable(), true)) {
            /** @var User $user */
            $user = request()->user();
            $data['updated_by'] = $user->getKey();
        }

        return new ($this->resourceClass())($this->repo()->update($model, $data));
    }

    protected function doDestroy(Model $model): JsonResponse
    {
        $this->authorize('delete', $model);

        $this->repo()->delete($model);

        return ApiResponse::success(['deleted' => true]);
    }
}
