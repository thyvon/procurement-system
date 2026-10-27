<?php

namespace Modules\Invoices\Http\Requests\Concerns;

use App\Support\Currency;
use Illuminate\Validation\Rule;
use Modules\Invoices\Models\Invoice;

/**
 * Shared invoice payload rules for Store/Update invoice requests. The line
 * list is always submitted whole (full-replace semantics); money columns
 * (subtotal, total) and `code`/`status`/`cash_reference` are server-owned
 * and never accepted — the cash link is submitted as `cash_request_id` and
 * the service derives the reference code from it.
 */
trait ValidatesInvoicePayload
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function invoiceRules(): array
    {
        return [
            'invoice_number' => ['required', 'string', 'max:64'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'supplier_code' => ['nullable', 'string', 'max:64'],
            'supplier_phone' => ['required', 'string', 'max:32'],
            'supplier_address' => ['required', 'string', 'max:255'],
            'transaction_type' => ['required', 'string', Rule::in(Invoice::TRANSACTION_TYPES)],
            'cash_request_id' => [
                'nullable',
                'string',
                'required_unless:transaction_type,'.Invoice::TRANSACTION_TYPE_CREDIT,
                Rule::exists('cash_requests', 'id')
                    ->where('entity_id', $this->user()?->entity_id)
                    ->whereNull('deleted_at'),
            ],
            'payment_term' => ['required', 'string', Rule::in(Invoice::PAYMENT_TERMS)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['sometimes', 'string', Rule::in(Currency::CODES)],
            'exchange_rate' => [
                'nullable',
                'required_if:currency,'.Currency::KHR,
                'numeric',
                'gt:0',
            ],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.uom' => ['required', 'string', 'max:32'],
            'lines.*.campus' => ['required', 'string', 'max:64'],
            'lines.*.division' => ['required', 'string', 'max:64'],
            'lines.*.department' => ['required', 'string', 'max:64'],
            'lines.*.location' => ['required', 'string', 'max:128'],
            'lines.*.purpose' => ['required', 'string', 'max:255'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Column-bound payload for the service (lines array intact).
     *
     * @return array<string, mixed>
     */
    public function invoiceData(): array
    {
        return [
            'invoice_number' => $this->validated('invoice_number'),
            'supplier_name' => $this->validated('supplier_name'),
            'supplier_code' => $this->validated('supplier_code'),
            'supplier_phone' => $this->validated('supplier_phone'),
            'supplier_address' => $this->validated('supplier_address'),
            'transaction_type' => $this->validated('transaction_type'),
            'cash_request_id' => $this->validated('cash_request_id'),
            'payment_term' => $this->validated('payment_term'),
            'invoice_date' => $this->validated('invoice_date'),
            'due_date' => $this->validated('due_date'),
            'currency' => $this->validated('currency'),
            'exchange_rate' => $this->validated('exchange_rate'),
            'vat_amount' => $this->validated('vat_amount'),
            'vat_percent' => $this->validated('vat_percent'),
            'discount_amount' => $this->validated('discount_amount'),
            'notes' => $this->validated('notes'),
            'lines' => array_values($this->validated('lines', [])),
        ];
    }
}
