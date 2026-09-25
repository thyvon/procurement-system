<?php

namespace Modules\Approvals\Repositories;

use App\Support\Repository\RepositoryInterface;
use Modules\Approvals\Models\TocaEntry;

interface TocaEntryRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<int, TocaEntry>
     */
    public function allWithUser(): array;
}
