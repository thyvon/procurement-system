<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Users\Http\Requests\StoreUserRequest;
use Modules\Users\Http\Requests\UpdateAvatarRequest;
use Modules\Users\Http\Requests\UpdateUserRequest;
use Modules\Users\Repositories\UserRepositoryInterface;
use Modules\Users\Services\AvatarService;
use Modules\Users\Services\UserRoleService;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserRoleService $roles,
        private readonly AvatarService $avatars,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection($this->users->paginate(50));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        /** @var User $actor */
        $actor = $request->user();

        $attributes = $request->userData();

        if ($actor->can('users.manage', 'sanctum') && $request->filled('entity_id')) {
            $attributes['entity_id'] = $request->string('entity_id')->toString();
        } else {
            $attributes['entity_id'] = $actor->entity_id;
        }

        $user = $this->users->create($attributes);
        $this->roles->syncRoles($user, $request->roleNames());

        return ApiResponse::success(new UserResource($user->load('roles')), 201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        /** @var User $actor */
        $actor = $request->user();

        $data = collect($request->validated())->except(['roles'])->all();

        if ($data !== []) {
            $this->users->update($user, $data);
        }

        if ($request->has('roles') && $actor->can('users.manage', 'sanctum')) {
            $this->roles->syncRoles($user, $request->input('roles'));
        }

        return new UserResource($user->refresh()->load('roles'));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->roles->deactivate($user);

        return ApiResponse::success(['deactivated' => true]);
    }

    public function updateAvatar(UpdateAvatarRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $this->avatars->replaceWithUpload($user, $request->file('image'));

        return new UserResource($user->refresh());
    }
}
