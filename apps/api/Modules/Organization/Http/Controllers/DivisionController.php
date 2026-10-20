<?php

namespace Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Organization\Http\Requests\StoreDivisionRequest;
use Modules\Organization\Http\Requests\UpdateDivisionRequest;
use Modules\Organization\Http\Resources\DivisionResource;
use Modules\Organization\Models\Division;
use Modules\Organization\Repositories\DivisionRepositoryInterface;
use Modules\Organization\Services\OrganizationLookupService;
use Modules\Products\Services\CodeGenerationService;

class DivisionController extends Controller
{
    use Concerns\InteractsWithOrgLookups;

    public function __construct(
        private readonly DivisionRepositoryInterface $repo,
        private readonly OrganizationLookupService $lookups,
        private readonly CodeGenerationService $codes,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return $this->doIndex();
    }

    public function store(StoreDivisionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $departmentIds = $data['department_ids'] ?? [];
        unset($data['department_ids']);

        $this->authorize('create', Division::class);

        $data['short_code'] ??= $this->codes->nextShortCode(
            $data['name'],
            Division::class,
            $request->user()?->entity_id,
        );

        /** @var User $user */
        $user = $request->user();

        $division = $this->repo->create([
            ...$data,
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        $this->lookups->syncDepartmentIds($division, $departmentIds);
        $division->load('departments:id');

        return ApiResponse::success(new DivisionResource($division), 201);
    }

    public function show(Division $division): DivisionResource
    {
        $this->authorize('view', $division);

        $division->load('departments:id');

        return new DivisionResource($division);
    }

    public function update(UpdateDivisionRequest $request, Division $division): DivisionResource
    {
        $data = $request->validated();

        if (array_key_exists('department_ids', $data)) {
            $this->lookups->syncDepartmentIds($division, $data['department_ids']);
            unset($data['department_ids']);
        }

        $this->doUpdate($division, $data);
        $division->load('departments:id');

        return new DivisionResource($division);
    }

    public function destroy(Division $division): JsonResponse
    {
        return $this->doDestroy($division);
    }

    protected function repo(): DivisionRepositoryInterface
    {
        return $this->repo;
    }

    protected function modelClass(): string
    {
        return Division::class;
    }

    protected function resourceClass(): string
    {
        return DivisionResource::class;
    }
}
