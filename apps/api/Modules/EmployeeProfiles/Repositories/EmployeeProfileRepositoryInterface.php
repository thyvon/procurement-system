<?php

namespace Modules\EmployeeProfiles\Repositories;

use App\Models\User;
use App\Support\Repository\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\EmployeeProfiles\Models\EmployeeProfile;

interface EmployeeProfileRepositoryInterface extends RepositoryInterface
{
    public function currentFor(User $user): ?EmployeeProfile;

    /**
     * @return Collection<int, EmployeeProfile>
     */
    public function historyFor(User $user): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): EmployeeProfile;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $profile, array $attributes): EmployeeProfile;
}
