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
    public function allWithUser(): array
    {
        return $this->query()
            ->with('user')
            ->orderBy('subject_type')
            ->orderBy('min_amount')
            ->orderBy('user_id')
            ->get()
            ->all();
    }
}
