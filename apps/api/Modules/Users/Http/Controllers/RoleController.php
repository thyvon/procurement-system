<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Users\Http\Requests\StoreRoleRequest;
use Modules\Users\Http\Requests\UpdateRoleRequest;
use Modules\Users\Http\Resources\RoleResource;
use Modules\Users\Repositories\RoleRepositoryInterface;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly RoleRepositoryInterface $roles) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        return RoleResource::collection($this->roles->allForGuard('sanctum'));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $role = $this->roles->create([
            'name' => $request->string('name')->toString(),
            'guard_name' => 'sanctum',
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->validated('permissions', []));
        }

        return ApiResponse::success(new RoleResource($role->load('permissions')), 201);
    }

    public function show(Role $role): RoleResource
    {
        $this->authorize('view', $role);

        return new RoleResource($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $this->authorize('update', $role);

        $data = $request->validated();
        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);

        if ($data !== []) {
            $this->roles->update($role, $data);
        }

        if ($permissions !== null) {
            $role->syncPermissions($permissions);
        }

        return new RoleResource($role->load('permissions'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        if ($role->name === 'admin') {
            throw ValidationException::withMessages([
                'role' => 'The admin role cannot be deleted.',
            ]);
        }

        $assignedCount = DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $role->getKey())
            ->where('model_type', (new User)->getMorphClass())
            ->count();

        if ($assignedCount > 0) {
            throw ValidationException::withMessages([
                'role' => "This role is still assigned to {$assignedCount} user(s) and cannot be deleted.",
            ]);
        }

        $this->roles->delete($role);

        return ApiResponse::success(['deleted' => true]);
    }
}
