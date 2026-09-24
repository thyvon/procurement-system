<?php

namespace Modules\EPurchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class EPurchaseItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => (string) ($this->resource['ItemCode'] ?? ''),
            'description' => (string) ($this->resource['Description'] ?? ''),
            'longDescription' => (string) ($this->resource['LongDescription'] ?? ''),
            'category' => (string) ($this->resource['category'] ?? ''),
            'subCategory' => (string) ($this->resource['sub_category'] ?? ''),
            'uom' => (string) ($this->resource['BaseItemUnit'] ?? ''),
            'estimatePrice' => is_numeric($this->resource['estimate_price'] ?? null)
                ? (float) $this->resource['estimate_price']
                : null,
            'avgPrice' => is_numeric($this->resource['avg_price_3_months'] ?? null)
                ? (float) $this->resource['avg_price_3_months']
                : null,
            'status' => (string) ($this->resource['Status'] ?? ''),
        ];
    }
}
