<?php

namespace Modules\Invoices\Http\Requests\Concerns;

use App\Support\Currency;
use Illuminate\Validation\Rule;

/**
 * Shared invoice payload rules for Store/Update invoice requests. The line
 * list is always submitted whole (full-replace semantics); money columns
 * (subtotal, total) and `code`/`status` are server-owned and never accepted.
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
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
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
            'invoice_date' => $this->validated('invoice_date'),
            'due_date' => $this->validated('due_date'),
            'currency' => $this->validated('currency'),
            'exchange_rate' => $this->validated('exchange_rate'),
            'vat_amount' => $this->validated('vat_amount'),
            'discount_amount' => $this->validated('discount_amount'),
            'notes' => $this->validated('notes'),
            'lines' => array_values($this->validated('lines', [])),
        ];
    }
}
