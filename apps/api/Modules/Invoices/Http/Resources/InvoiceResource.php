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
     *     supplierPhone: string|null,
     *     supplierAddress: string|null,
     *     transactionType: string|null,
     *     cashReference: string|null,
     *     cashRequestId: string|null,
     *     paymentTerm: string|null,
     *     invoiceDate: string|null,
     *     dueDate: string|null,
     *     currency: string,
     *     exchangeRate: float,
     *     subtotal: float,
     *     vatAmount: float,
     *     vatPercent: float|null,
     *     discountAmount: float,
     *     total: float,
     *     grandTotalUsd: float,
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
            'supplierPhone' => $this->supplier_phone,
            'supplierAddress' => $this->supplier_address,
            'transactionType' => $this->transaction_type,
            'cashReference' => $this->cash_reference,
            'cashRequestId' => $this->cash_request_id,
            'paymentTerm' => $this->payment_term,
            'invoiceDate' => $this->invoice_date?->format('Y-m-d'),
            'dueDate' => $this->due_date?->format('Y-m-d'),
            'currency' => (string) $this->currency,
            'exchangeRate' => (float) $this->exchange_rate,
            'subtotal' => (float) $this->subtotal,
            'vatAmount' => (float) $this->vat_amount,
            'vatPercent' => $this->vat_percent !== null ? (float) $this->vat_percent : null,
            'discountAmount' => (float) $this->discount_amount,
            'total' => (float) $this->total,
            'grandTotalUsd' => (float) $this->grand_total_usd,
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
