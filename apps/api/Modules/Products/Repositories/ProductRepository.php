<?php

namespace Modules\Products\Repositories;

use App\Support\Context\EntityContext;
use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Builder as ScoutBuilder;
use Modules\Products\Models\Product;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()
            ->with(['category', 'group', 'brand', 'uom'])
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($perPage);
    }

    public function filtered(array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = $this->query()->with(['category', 'group', 'brand', 'uom']);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $ids = $this->searchIds($search, 1000);
            if ($ids !== []) {
                $query->whereIn('id', $ids);
            } else {
                // Meilisearch reachable but no hits — nothing can match.
                return Product::query()->whereRaw('1 = 0')->paginate($perPage, ['*'], 'page', $page);
            }
        }

        if (! empty($filters['category_id'])) {
            $query->where('product_category_id', $filters['category_id']);
        }

        if (! empty($filters['group_id'])) {
            $query->where('product_group_id', $filters['group_id']);
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (! empty($filters['type'])) {
            $query->where('product_type', $filters['type']);
        }

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function searchIds(string $query, int $limit = 50): array
    {
        try {
            /** @var ScoutBuilder $builder */
            $builder = Product::search($query)
                ->options(['limit' => $limit]);

            $entityId = app(EntityContext::class)->entityId();

            if ($entityId !== null) {
                $builder->options(['filter' => ['entity_id = '.$entityId]]);
            }

            return collect($builder->keys())->all();
        } catch (\Throwable $e) {
            // Search engine down — degrade to SQL LIKE so the list stays usable.
            Log::warning('Product search fell back to SQL: '.$e->getMessage());

            return $this->query()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('name_km', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "{$query}%");
                })
                ->limit($limit)
                ->pluck('id')
                ->all();
        }
    }

    public function options(): array
    {
        return $this->query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'name_km'])
            ->all();
    }
}
