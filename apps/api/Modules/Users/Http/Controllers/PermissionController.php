<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Users\Http\Resources\PermissionResource;
use Modules\Users\Repositories\PermissionRepositoryInterface;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(private readonly PermissionRepositoryInterface $permissions) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Permission::class);

        return PermissionResource::collection($this->permissions->allForGuard('sanctum'));
    }
}
