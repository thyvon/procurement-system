<?php

namespace Modules\Approvals\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Approvals\Http\Requests\StoreTocaEntryRequest;
use Modules\Approvals\Http\Requests\UpdateTocaEntryRequest;
use Modules\Approvals\Http\Resources\TocaEntryResource;
use Modules\Approvals\Models\TocaEntry;
use Modules\Approvals\Repositories\TocaEntryRepositoryInterface;

class TocaEntryController extends Controller
{
    public function __construct(
        private readonly TocaEntryRepositoryInterface $repo,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', TocaEntry::class);

        return ApiResponse::success(TocaEntryResource::collection($this->repo->allWithUser()));
    }

    public function store(StoreTocaEntryRequest $request): JsonResponse
    {
        $this->authorize('create', TocaEntry::class);

        /** @var User $user */
        $user = $request->user();

        $entry = $this->repo->create([
            ...$request->validated(),
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        return ApiResponse::success(new TocaEntryResource($entry->load('user')), 201);
    }

    public function show(TocaEntry $entry): JsonResponse
    {
        $this->authorize('view', $entry);

        return ApiResponse::success(new TocaEntryResource($entry->load('user')));
    }

    public function update(UpdateTocaEntryRequest $request, TocaEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry);

        /** @var User $user */
        $user = $request->user();

        $entry = $this->repo->update($entry, [
            ...$request->validated(),
            'updated_by' => $user->getKey(),
        ]);

        return ApiResponse::success(new TocaEntryResource($entry->load('user')));
    }

    public function destroy(TocaEntry $entry): JsonResponse
    {
        $this->authorize('delete', $entry);

        $this->repo->delete($entry);

        return ApiResponse::success(['deleted' => true]);
    }
}
