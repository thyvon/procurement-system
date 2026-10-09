<?php

namespace Modules\EPurchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class EPurchasePrResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) ($this->resource['id'] ?? 0),
            'no' => (int) ($this->resource['no'] ?? 0),
            'refNum' => (string) ($this->resource['RefNum'] ?? ''),
            'purpose' => (string) ($this->resource['Purpose'] ?? ''),
            'amount' => is_numeric($this->resource['amount'] ?? null)
                ? (float) $this->resource['amount']
                : null,
            'requester' => (string) ($this->resource['requester'] ?? ''),
            'createdAt' => (string) ($this->resource['created_at'] ?? ''),
            'status' => is_string($this->resource['status'] ?? null)
                ? $this->resource['status']
                : null,
            'purchaseStatus' => is_string($this->resource['purchase_status'] ?? null)
                ? $this->resource['purchase_status']
                : null,
        ];
    }
}
