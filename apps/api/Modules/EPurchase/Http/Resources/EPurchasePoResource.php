<?php

namespace Modules\EPurchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class EPurchasePoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) ($this->resource['id'] ?? 0),
            'no' => (int) ($this->resource['no'] ?? 0),
            'poRefNum' => (string) ($this->resource['poRefNum'] ?? ''),
            'vendorName' => (string) ($this->resource['vendorName'] ?? ''),
            'purpose' => (string) ($this->resource['purpose'] ?? ''),
            'amount' => is_numeric($this->resource['amount'] ?? null)
                ? (float) $this->resource['amount']
                : null,
            'currency' => (string) ($this->resource['currency'] ?? ''),
            'prepareByName' => (string) ($this->resource['prepareByName'] ?? ''),
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
