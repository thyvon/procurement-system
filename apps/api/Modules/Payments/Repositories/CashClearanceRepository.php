<?php

namespace Modules\Payments\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Payments\Models\CashClearance;

class CashClearanceRepository extends BaseRepository implements CashClearanceRepositoryInterface
{
    public function __construct(CashClearance $model)
    {
        parent::__construct($model);
    }

    public function filtered(array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = $this->query()->with(['lines', 'creator', 'cashRequest']);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('code', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderByDesc('code')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
