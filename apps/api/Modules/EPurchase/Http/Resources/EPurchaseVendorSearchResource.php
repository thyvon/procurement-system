<?php

namespace Modules\EPurchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class EPurchaseVendorSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) ($this->resource['id'] ?? 0),
            'code' => (string) ($this->resource['SupplierCode'] ?? ''),
            'nameEn' => (string) ($this->resource['name_en'] ?? ''),
            'nameKhmer' => (string) ($this->resource['name_kh'] ?? ''),
            'text' => (string) ($this->resource['text'] ?? ''),
        ];
    }
}
