<?php

namespace Modules\Products\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Products\Models\VariationTemplate;

class VariationTemplateRepository extends BaseRepository implements VariationTemplateRepositoryInterface
{
    public function __construct(VariationTemplate $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()->with('options')->orderBy('name')->cursorPaginate($perPage);
    }

    public function allWithOptions(): array
    {
        return $this->query()->with('options')->orderBy('name')->get()->all();
    }
}
