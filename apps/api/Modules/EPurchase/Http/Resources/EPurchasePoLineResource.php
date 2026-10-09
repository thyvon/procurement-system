<?php

namespace Modules\EPurchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class EPurchasePoLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) ($this->resource['id'] ?? 0),
            'prRefNum' => (string) ($this->resource['pr_refnum'] ?? ''),
            'itemCode' => (string) ($this->resource['item_code'] ?? ''),
            'description' => (string) ($this->resource['description'] ?? ''),
            'description2' => (string) ($this->resource['description2'] ?? ''),
            'poDescription' => is_scalar($this->resource['po_description'] ?? null)
                ? (string) $this->resource['po_description']
                : null,
            'qty' => is_numeric($this->resource['qty'] ?? null)
                ? (float) $this->resource['qty']
                : null,
            'unit' => (string) ($this->resource['unit'] ?? ''),
            'campusCode' => (string) ($this->resource['campus_code'] ?? ''),
            'divisionCode' => (string) ($this->resource['division_code'] ?? ''),
            'departmentCode' => (string) ($this->resource['department_code'] ?? ''),
            'location' => (string) ($this->resource['location'] ?? ''),
            'unitCost' => is_numeric($this->resource['unitCost'] ?? null)
                ? (float) $this->resource['unitCost']
                : null,
            'deliveryFee' => is_numeric($this->resource['delivery_fee'] ?? null)
                ? (float) $this->resource['delivery_fee']
                : null,
            'discount' => is_numeric($this->resource['discount'] ?? null)
                ? (float) $this->resource['discount']
                : null,
            'vat' => is_numeric($this->resource['vat'] ?? null)
                ? (float) $this->resource['vat']
                : null,
            'usdAmount' => is_numeric($this->resource['usdAmount'] ?? null)
                ? (float) $this->resource['usdAmount']
                : null,
            'purchaseQty' => is_numeric($this->resource['purchase_qty'] ?? null)
                ? (float) $this->resource['purchase_qty']
                : null,
            'cancelQty' => is_numeric($this->resource['cancel_qty'] ?? null)
                ? (float) $this->resource['cancel_qty']
                : null,
            'pendingQty' => is_numeric($this->resource['pending_qty'] ?? null)
                ? (float) $this->resource['pending_qty']
                : null,
            'currency' => (string) ($this->resource['currency'] ?? ''),
            'status' => is_scalar($this->resource['status'] ?? null)
                ? (string) $this->resource['status']
                : null,
            'forceClose' => (int) ($this->resource['force_close'] ?? 0),
        ];
    }
}
