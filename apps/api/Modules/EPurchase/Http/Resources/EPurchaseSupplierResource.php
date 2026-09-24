<?php

namespace Modules\EPurchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'nameEn' => (string) ($this->resource['name_en'] ?? ''),
            'nameKhmer' => (string) ($this->resource['name_kh'] ?? ''),
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
