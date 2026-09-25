<?php

namespace Modules\PurchaseOrders\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\PurchaseOrders\Models\Evaluation;

class EvaluationRepository extends BaseRepository implements EvaluationRepositoryInterface
{
    public function __construct(Evaluation $model)
    {
        parent::__construct($model);
    }

    public function filtered(array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = $this->query()->with(['quotations.lines', 'items', 'creator']);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('quotations', fn (Builder $quotations): Builder => $quotations
                        ->where('supplier_name', 'like', "%{$search}%")
                        ->orWhere('supplier_code', 'like', "%{$search}%"));
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
