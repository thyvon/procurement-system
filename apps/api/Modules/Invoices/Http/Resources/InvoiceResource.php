<?php

namespace Modules\Invoices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Modules\Invoices\Models\Invoice;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     code: string,
     *     invoiceNumber: string,
     *     status: string,
     *     supplierName: string,
     *     supplierCode: string|null,
     *     invoiceDate: string|null,
     *     dueDate: string|null,
     *     currency: string,
     *     exchangeRate: float,
     *     subtotal: float,
     *     vatAmount: float,
     *     discountAmount: float,
     *     total: float,
     *     notes: string|null,
     *     paidAt: string|null,
     *     updatedAt: string|null,
     *     createdBy: string|null,
     *     createdById: int|null,
     *     lines: MissingValue|Collection,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'invoiceNumber' => $this->invoice_number,
            'status' => $this->status,
            'supplierName' => $this->supplier_name,
            'supplierCode' => $this->supplier_code,
            'invoiceDate' => $this->invoice_date?->format('Y-m-d'),
            'dueDate' => $this->due_date?->format('Y-m-d'),
            'currency' => (string) $this->currency,
            'exchangeRate' => (float) $this->exchange_rate,
            'subtotal' => (float) $this->subtotal,
            'vatAmount' => (float) $this->vat_amount,
            'discountAmount' => (float) $this->discount_amount,
            'total' => (float) $this->total,
            'notes' => $this->notes,
            'paidAt' => $this->paid_at?->format('Y-m-d H:i'),
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i'),
            'createdBy' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'createdById' => $this->created_by,
            'lines' => $this->relationLoaded('lines')
                ? InvoiceLineResource::collection($this->lines)
                : new MissingValue,
        ];
    }
}
