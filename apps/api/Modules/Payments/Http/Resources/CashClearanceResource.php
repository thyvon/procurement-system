<?php

namespace Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Modules\Payments\Models\CashClearance;

/**
 * @mixin CashClearance
 */
class CashClearanceResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     code: string,
     *     status: string,
     *     cashRequestId: string,
     *     cashRequestCode: string|null,
     *     clearanceDate: string|null,
     *     exchangeRate: float,
     *     totalUsd: float,
     *     requestAmountUsd: float,
     *     balanceUsd: float,
     *     updatedAt: string|null,
     *     createdBy: string|null,
     *     createdById: int|null,
     *     lines: MissingValue|Collection,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'cashRequestId' => $this->cash_request_id,
            'cashRequestCode' => $this->whenLoaded('cashRequest', fn () => $this->cashRequest?->code),
            'clearanceDate' => $this->clearance_date?->format('Y-m-d'),
            'exchangeRate' => (float) $this->exchange_rate,
            'totalUsd' => (float) $this->total_usd,
            'requestAmountUsd' => (float) $this->request_amount_usd,
            'balanceUsd' => (float) $this->balance_usd,
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i'),
            'createdBy' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'createdById' => $this->created_by,
            'lines' => $this->relationLoaded('lines')
                ? CashClearanceLineResource::collection($this->lines)
                : new MissingValue,
        ];
    }
}
