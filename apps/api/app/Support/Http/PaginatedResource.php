<?php

namespace App\Support\Http;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class PaginatedResource extends JsonResource
{
    /**
     * @param  CursorPaginator<int, mixed>|LengthAwarePaginator<int, mixed>  $paginator
     * @return array<string, mixed>
     */
    public static function collectionPayload(CursorPaginator|LengthAwarePaginator $paginator, callable $mapper): array
    {
        $items = collect($paginator->items())->map(fn ($model) => $mapper($model))->values()->all();

        $meta = [
            'items' => count($items),
            'hasMore' => (bool) $paginator->hasMorePages(),
        ];

        if ($paginator instanceof CursorPaginator) {
            $meta['nextCursor'] = $paginator->nextCursor()?->encode();
            $meta['previousCursor'] = $paginator->previousCursor()?->encode();
        } else {
            $meta['currentPage'] = $paginator->currentPage();
            $meta['lastPage'] = $paginator->lastPage();
            $meta['total'] = $paginator->total();
        }

        return ['data' => $items, 'meta' => $meta];
    }
}
