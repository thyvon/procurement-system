<?php

namespace Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payments\Models\AdvanceLiquidationLine;

/**
 * @mixin AdvanceLiquidationLine
 */
class AdvanceLiquidationLineResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     description: string,
     *     currency: string|null,
     *     amount: float,
     *     amountUsd: float,
     *     position: int,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'amountUsd' => (float) $this->amount_usd,
            'position' => $this->position,
        ];
    }
}
