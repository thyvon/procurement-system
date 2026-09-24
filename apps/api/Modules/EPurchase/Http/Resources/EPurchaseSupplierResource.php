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
            'code' => (string) ($this->resource['code'] ?? ''),
            'name' => SupplierName::resolve($this->resource),
            'phone' => (string) ($this->resource['phone'] ?? ''),
            'address' => (string) ($this->resource['address'] ?? ''),
            'email' => (string) ($this->resource['email'] ?? ''),
            'supplierType' => (string) ($this->resource['supplier_type'] ?? ''),
            'paymentTerm' => (string) ($this->resource['payment_term'] ?? ''),
            'isOnboard' => (string) ($this->resource['is_onboard'] ?? ''),
            'status' => (string) ($this->resource['status'] ?? ''),
        ];
    }
}
