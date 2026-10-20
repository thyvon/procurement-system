<?php

namespace Modules\Payments\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Payments\Models\CashRequest;

class CashRequestRepository extends BaseRepository implements CashRequestRepositoryInterface
{
    public function __construct(CashRequest $model)
    {
        parent::__construct($model);
    }

    public function filtered(array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = $this->query()->with(['lines', 'creator']);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('campus', 'like', "%{$search}%")
                    ->orWhere('division', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('remark', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query
            ->orderByDesc('code')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
