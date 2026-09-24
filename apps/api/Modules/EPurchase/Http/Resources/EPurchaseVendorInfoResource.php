<?php

namespace Modules\EPurchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class EPurchaseVendorInfoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) ($this->resource['id'] ?? 0),
            'code' => (string) ($this->resource['code'] ?? ''),
            'nameEn' => (string) ($this->resource['name_en'] ?? ''),
            'nameKhmer' => (string) ($this->resource['name_kh'] ?? ''),
            'phone' => (string) ($this->resource['phone'] ?? ''),
            'address' => (string) ($this->resource['address'] ?? ''),
            'email' => (string) ($this->resource['email'] ?? ''),
            'paymentTerm' => (string) ($this->resource['payment_term'] ?? ''),
            'vatPercentage' => is_numeric($this->resource['vat_percentage'] ?? null)
                ? (string) (0 + $this->resource['vat_percentage'])
                : (string) ($this->resource['vat_percentage'] ?? ''),
        ];
    }
}
