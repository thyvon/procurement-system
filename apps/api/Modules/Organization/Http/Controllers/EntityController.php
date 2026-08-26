<?php

namespace Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Organization\Http\Requests\StoreEntityRequest;
use Modules\Organization\Http\Requests\UpdateEntityRequest;
use Modules\Organization\Http\Resources\EntityResource;
use Modules\Organization\Models\Entity;
use Modules\Organization\Repositories\EntityRepositoryInterface;

class EntityController extends Controller
{
    public function __construct(private readonly EntityRepositoryInterface $entities) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Entity::class);

        return EntityResource::collection($this->entities->paginate(50));
    }

    public function store(StoreEntityRequest $request): JsonResponse
    {
        $this->authorize('create', Entity::class);

        /** @var User $user */
        $user = $request->user();

        $entity = $this->entities->create([
            ...$request->validated(),
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        return ApiResponse::success(new EntityResource($entity), 201);
    }

    public function show(Entity $entity): EntityResource
    {
        $this->authorize('view', $entity);

        return new EntityResource($entity);
    }

    public function update(UpdateEntityRequest $request, Entity $entity): EntityResource
    {
        $this->authorize('update', $entity);

        /** @var User $user */
        $user = $request->user();

        $updated = $this->entities->update($entity, [
            ...$request->validated(),
            'updated_by' => $user->getKey(),
        ]);

        return new EntityResource($updated);
    }

    public function destroy(Entity $entity): JsonResponse
    {
        $this->authorize('delete', $entity);

        $this->entities->delete($entity);

        return ApiResponse::success(['deleted' => true]);
    }
}
