<?php

namespace Modules\EPurchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class EPurchasePrLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) ($this->resource['id'] ?? 0),
            'itemCode' => (string) ($this->resource['item_code'] ?? ''),
            'description' => (string) ($this->resource['description'] ?? ''),
            'description3' => (string) ($this->resource['description3'] ?? ''),
            'campusCode' => (string) ($this->resource['campus_code'] ?? ''),
            'divisionCode' => (string) ($this->resource['division_code'] ?? ''),
            'departmentCode' => (string) ($this->resource['department_code'] ?? ''),
            'qty' => is_numeric($this->resource['qty'] ?? null)
                ? (float) $this->resource['qty']
                : null,
            'unitType' => (string) ($this->resource['unit_type'] ?? ''),
            'unitPrice' => is_numeric($this->resource['unit_price'] ?? null)
                ? (float) $this->resource['unit_price']
                : null,
            'subTotal' => is_numeric($this->resource['sub_total'] ?? null)
                ? (float) $this->resource['sub_total']
                : null,
            'currency' => (string) ($this->resource['currency'] ?? ''),
            'status' => is_scalar($this->resource['status'] ?? null)
                ? (string) $this->resource['status']
                : null,
            'canceled' => (int) ($this->resource['canceled'] ?? 0),
            'received' => (int) ($this->resource['received'] ?? 0),
            'purchaseOrderQty' => (int) ($this->resource['purchase_order_qty'] ?? 0),
            'remainAfterPo' => (int) ($this->resource['remain_after_po'] ?? 0),
            'forceClose' => (int) ($this->resource['force_close'] ?? 0),
        ];
    }
}
