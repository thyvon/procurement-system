<?php

namespace Modules\Invoices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Invoices\Models\InvoiceLine;

/**
 * @mixin InvoiceLine
 */
class InvoiceLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'uom' => $this->uom,
            'campus' => $this->campus,
            'division' => $this->division,
            'department' => $this->department,
            'location' => $this->location,
            'purpose' => $this->purpose,
            'qty' => (float) $this->qty,
            'unitPrice' => (float) $this->unit_price,
            'discount' => (float) $this->discount,
            'lineTotal' => (float) $this->line_total,
            'grandTotalUsd' => (float) $this->grand_total_usd,
            'position' => $this->position,
        ];
    }
}
