<?php

namespace Modules\Organization\Repositories;

use App\Support\Repository\BaseRepository;
use Modules\Organization\Models\Entity;

class EntityRepository extends BaseRepository implements EntityRepositoryInterface
{
    public function __construct(Entity $model)
    {
        parent::__construct($model);
    }

    public function findByCode(string $code): ?Entity
    {
        return $this->query()->where('code', $code)->first();
    }
}
