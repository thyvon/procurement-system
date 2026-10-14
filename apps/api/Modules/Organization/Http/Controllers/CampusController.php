<?php

namespace Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Organization\Http\Requests\StoreCampusRequest;
use Modules\Organization\Http\Requests\UpdateCampusRequest;
use Modules\Organization\Http\Resources\CampusResource;
use Modules\Organization\Models\Campus;
use Modules\Organization\Repositories\CampusRepositoryInterface;

class CampusController extends Controller
{
    use Concerns\InteractsWithOrgLookups;

    public function __construct(
        private readonly CampusRepositoryInterface $repo,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return $this->doIndex();
    }

    public function store(StoreCampusRequest $request): JsonResponse
    {
        return $this->doStore($request->validated());
    }

    public function show(Campus $campus): CampusResource
    {
        $this->authorize('view', $campus);

        return new CampusResource($campus);
    }

    public function update(UpdateCampusRequest $request, Campus $campus): mixed
    {
        return $this->doUpdate($campus, $request->validated());
    }

    public function destroy(Campus $campus): JsonResponse
    {
        return $this->doDestroy($campus);
    }

    protected function repo(): CampusRepositoryInterface
    {
        return $this->repo;
    }

    protected function modelClass(): string
    {
        return Campus::class;
    }

    protected function resourceClass(): string
    {
        return CampusResource::class;
    }
}
