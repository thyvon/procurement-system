<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Products\Http\Requests\StoreUomRequest;
use Modules\Products\Http\Requests\UpdateUomRequest;
use Modules\Products\Http\Resources\UomResource;
use Modules\Products\Models\Uom;
use Modules\Products\Models\UomSubUnit;
use Modules\Products\Repositories\UomRepositoryInterface;

class UomController extends Controller
{
    public function __construct(private readonly UomRepositoryInterface $repo) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Uom::class);

        return UomResource::collection($this->repo->paginate(100));
    }

    public function store(StoreUomRequest $request): JsonResponse
    {
        $this->authorize('create', Uom::class);

        /** @var User $user */
        $user = $request->user();

        $uom = DB::transaction(function () use ($request, $user) {
            $data = $request->validated();
            $subUnits = $data['sub_units'] ?? [];
            unset($data['sub_units']);

            $uom = $this->repo->create([...$data, 'created_by' => $user->getKey(), 'updated_by' => $user->getKey()]);
            $this->syncSubUnits($uom, $subUnits);

            return $uom;
        });

        return ApiResponse::success(new UomResource($uom->load('subUnits')), 201);
    }

    public function show(Uom $uom): UomResource
    {
        $this->authorize('view', $uom);

        return new UomResource($uom->load('subUnits'));
    }

    public function update(UpdateUomRequest $request, Uom $uom): UomResource
    {
        $this->authorize('update', $uom);

        /** @var User $user */
        $user = $request->user();

        $uom = DB::transaction(function () use ($request, $user, $uom) {
            $data = $request->validated();
            $subUnits = $data['sub_units'] ?? null;
            unset($data['sub_units']);

            $data['updated_by'] = $user->getKey();
            $updated = $this->repo->update($uom, $data);

            if ($subUnits !== null) {
                $this->syncSubUnits($updated, $subUnits);
            }

            return $updated;
        });

        return new UomResource($uom->load('subUnits'));
    }

    public function destroy(Uom $uom): JsonResponse
    {
        $this->authorize('delete', $uom);

        $this->repo->delete($uom);

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $subUnits
     */
    private function syncSubUnits(Uom $uom, array $subUnits): void
    {
        $keepIds = [];

        foreach ($subUnits as $row) {
            $subUnit = ! empty($row['id'])
                ? $uom->subUnits()->findOrFail($row['id'])
                : new UomSubUnit;

            $subUnit->forceFill([
                'entity_id' => $uom->entity_id,
                'uom_id' => $uom->getKey(),
                'name' => $row['name'],
                'short_name' => $row['short_name'],
                'conversion_factor' => $row['conversion_factor'],
                'is_active' => true,
            ]);
            $subUnit->save();
            $keepIds[] = $subUnit->getKey();
        }

        $uom->subUnits()
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
