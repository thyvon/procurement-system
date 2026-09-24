<?php

namespace Modules\EPurchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\EPurchase\Services\SupplierName;

/**
 * @mixin array<string, mixed>
 */
class EPurchaseSupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) ($this->resource['id'] ?? 0),
            'code' => (string) ($this->resource['code'] ?? ''),
            'name' => SupplierName::resolve($this->resource),
            'phone' => (string) ($this->resource['phone'] ?? ''),
            'address' => (string) ($this->resource['address'] ?? ''),
            'email' => (string) ($this->resource['email'] ?? ''),
            'supplierType' => (string) ($this->resource['supplier_type'] ?? ''),
            'paymentTerm' => (string) ($this->resource['payment_term'] ?? ''),
            'isOnboard' => (string) ($this->resource['is_onboard'] ?? ''),
            'status' => (string) ($this->resource['status'] ?? ''),
            'vatPercentage' => is_numeric($this->resource['vat_percentage'] ?? null)
                ? (string) (0 + $this->resource['vat_percentage'])
                : '',
        ];
    }
}
