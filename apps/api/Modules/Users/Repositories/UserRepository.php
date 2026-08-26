<?php

namespace Modules\Users\Repositories;

use App\Models\User;
use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Entity scoping is applied by the BelongsToEntity global scope,
     * reading the request's EntityContext.
     */
    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()
            ->with('roles')
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($perPage);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }
}
