<?php

namespace Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Modules\Payments\Models\AdvanceLiquidation;

/**
 * @mixin AdvanceLiquidation
 */
class AdvanceLiquidationResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     code: string,
     *     status: string,
     *     cashRequestId: string,
     *     cashRequestCode: string|null,
     *     liquidationDate: string|null,
     *     exchangeRate: float,
     *     campus: string,
     *     division: string,
     *     department: string,
     *     totalActualExpenseUsd: float,
     *     totalCashAdvanceUsd: float,
     *     remainingCashUsd: float,
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
            'liquidationDate' => $this->liquidation_date?->format('Y-m-d'),
            'exchangeRate' => (float) $this->exchange_rate,
            'campus' => $this->campus,
            'division' => $this->division,
            'department' => $this->department,
            'totalActualExpenseUsd' => (float) $this->total_actual_expense_usd,
            'totalCashAdvanceUsd' => (float) $this->total_cash_advance_usd,
            'remainingCashUsd' => (float) $this->remaining_cash_usd,
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i'),
            'createdBy' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'createdById' => $this->created_by,
            'lines' => $this->relationLoaded('lines')
                ? AdvanceLiquidationLineResource::collection($this->lines)
                : new MissingValue,
        ];
    }
}
