<?php

namespace Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payments\Models\CashClearanceLine;

/**
 * @mixin CashClearanceLine
 */
class CashClearanceLineResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     invoiceDate: string|null,
     *     prRef: string|null,
     *     purchaser: string|null,
     *     description: string,
     *     vendor: string,
     *     vendorPhone: string|null,
     *     campus: string,
     *     division: string,
     *     department: string,
     *     qty: float,
     *     uom: string,
     *     unitPriceUsd: float,
     *     discountUsd: float,
     *     vatUsd: float,
     *     amountUsd: float,
     *     position: int,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceDate' => $this->invoice_date?->format('Y-m-d'),
            'prRef' => $this->pr_ref,
            'purchaser' => $this->purchaser,
            'description' => $this->description,
            'vendor' => $this->vendor,
            'vendorPhone' => $this->vendor_phone,
            'campus' => $this->campus,
            'division' => $this->division,
            'department' => $this->department,
            'qty' => (float) $this->qty,
            'uom' => $this->uom,
            'unitPriceUsd' => (float) $this->unit_price_usd,
            'discountUsd' => (float) $this->discount_usd,
            'vatUsd' => (float) $this->vat_usd,
            'amountUsd' => (float) $this->amount_usd,
            'position' => $this->position,
        ];
    }
}
