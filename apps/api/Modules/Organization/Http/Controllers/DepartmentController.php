<?php

namespace Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Organization\Http\Requests\StoreDepartmentRequest;
use Modules\Organization\Http\Requests\UpdateDepartmentRequest;
use Modules\Organization\Http\Resources\DepartmentResource;
use Modules\Organization\Models\Department;
use Modules\Organization\Repositories\DepartmentRepositoryInterface;
use Modules\Organization\Services\OrganizationLookupService;

class DepartmentController extends Controller
{
    use Concerns\InteractsWithOrgLookups;

    public function __construct(
        private readonly DepartmentRepositoryInterface $repo,
        private readonly OrganizationLookupService $lookups,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return $this->doIndex();
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $divisionIds = $data['division_ids'] ?? [];
        unset($data['division_ids']);

        $this->authorize('create', Department::class);

        /** @var User $user */
        $user = $request->user();

        $department = $this->repo->create([
            ...$data,
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        $this->lookups->syncDivisionIds($department, $divisionIds);
        $department->load('divisions:id');

        return ApiResponse::success(new DepartmentResource($department), 201);
    }

    public function show(Department $department): DepartmentResource
    {
        $this->authorize('view', $department);

        $department->load('divisions:id');

        return new DepartmentResource($department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $data = $request->validated();

        if (array_key_exists('division_ids', $data)) {
            $this->lookups->syncDivisionIds($department, $data['division_ids']);
            unset($data['division_ids']);
        }

        $this->doUpdate($department, $data);
        $department->load('divisions:id');

        return new DepartmentResource($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        return $this->doDestroy($department);
    }

    protected function repo(): DepartmentRepositoryInterface
    {
        return $this->repo;
    }

    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function resourceClass(): string
    {
        return DepartmentResource::class;
    }
}
