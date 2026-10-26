<?php

namespace Modules\EmployeeProfiles\Repositories;

use App\Models\User;
use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\EmployeeProfiles\Models\EmployeeProfile;

class EmployeeProfileRepository extends BaseRepository implements EmployeeProfileRepositoryInterface
{
    public function __construct(EmployeeProfile $model)
    {
        parent::__construct($model);
    }

    public function currentFor(User $user): ?EmployeeProfile
    {
        return $this->query()
            ->with(['campus', 'division', 'department'])
            ->where('user_id', $user->getKey())
            ->where('is_current', true)
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return Collection<int, EmployeeProfile>
     */
    public function historyFor(User $user): Collection
    {
        return $this->query()
            ->with(['campus', 'division', 'department'])
            ->where('user_id', $user->getKey())
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): EmployeeProfile
    {
        /** @var EmployeeProfile $profile */
        $profile = $this->model->newInstance($attributes);
        $profile->save();

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $profile, array $attributes): EmployeeProfile
    {
        /** @var EmployeeProfile $profile */
        $profile->fill($attributes)->save();

        return $profile;
    }
}
