<?php

namespace Modules\Approvals\Repositories;

use App\Support\Repository\BaseRepository;
use Modules\Approvals\Models\TocaEntry;

class TocaEntryRepository extends BaseRepository implements TocaEntryRepositoryInterface
{
    public function __construct(TocaEntry $model)
    {
        parent::__construct($model);
    }

    /**
     * @return array<int, TocaEntry>
     */
    public function allWithUsers(): array
    {
        return $this->query()
            ->with('users:id,name')
            ->orderBy('subject_type')
            ->orderBy('min_amount')
            ->orderBy('name')
            ->get()
            ->all();
    }
}
