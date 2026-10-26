<?php

namespace Modules\EmployeeProfiles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\EmployeeProfiles\Http\Requests\StoreEmployeeProfileRequest;
use Modules\EmployeeProfiles\Http\Resources\EmployeeProfileResource;
use Modules\EmployeeProfiles\Repositories\EmployeeProfileRepositoryInterface;
use Modules\EmployeeProfiles\Services\EmployeeProfileService;

class EmployeeProfileController extends Controller
{
    public function __construct(
        private readonly EmployeeProfileRepositoryInterface $profiles,
        private readonly EmployeeProfileService $service,
    ) {}

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $profile = $this->profiles->currentFor($user);

        return ApiResponse::success(
            $profile !== null ? new EmployeeProfileResource($profile) : null,
        );
    }

    public function index(User $user): AnonymousResourceCollection
    {
        $this->authorize('view', $user);

        return EmployeeProfileResource::collection($this->profiles->historyFor($user));
    }

    public function store(StoreEmployeeProfileRequest $request, User $user): JsonResponse
    {
        $this->authorize('manageEmployeeProfile', $user);

        /** @var User $actor */
        $actor = $request->user();

        $profile = $this->service->record($user, $request->profileData(), $actor);

        return ApiResponse::success(new EmployeeProfileResource($profile), 201);
    }
}
