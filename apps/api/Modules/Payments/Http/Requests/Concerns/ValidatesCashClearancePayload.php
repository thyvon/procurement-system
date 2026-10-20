<?php

namespace Modules\Payments\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Shared cash clearance payload rules for Store/Update cash clearance requests. The line
 * list is always submitted whole (full-replace semantics); money columns
 * (total_usd, request_amount_usd, balance_usd, line amount_usd) and `code`/`status`
 * are server-owned and never accepted.
 */
trait ValidatesCashClearancePayload
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function cashClearanceRules(): array
    {
        return [
            'cash_request_id' => [
                'required',
                'string',
                Rule::exists('cash_requests', 'id')
                    ->where('entity_id', $this->user()?->entity_id)
                    ->whereNull('deleted_at'),
            ],
            'clearance_date' => ['required', 'date'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.invoice_date' => ['required', 'date'],
            'lines.*.pr_ref' => ['nullable', 'string', 'max:64'],
            'lines.*.purchaser' => ['nullable', 'string', 'max:64'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.vendor' => ['required', 'string', 'max:255'],
            'lines.*.vendor_phone' => ['nullable', 'string', 'max:32'],
            'lines.*.campus' => ['required', 'string', 'max:64'],
            'lines.*.division' => ['required', 'string', 'max:64'],
            'lines.*.department' => ['required', 'string', 'max:64'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.uom' => ['required', 'string', 'max:32'],
            'lines.*.unit_price_usd' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_usd' => ['nullable', 'numeric', 'min:0'],
            'lines.*.vat_usd' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Column-bound payload for the service (lines array intact).
     *
     * @return array<string, mixed>
     */
    public function cashClearanceData(): array
    {
        return [
            'cash_request_id' => $this->validated('cash_request_id'),
            'clearance_date' => $this->validated('clearance_date'),
            'exchange_rate' => $this->validated('exchange_rate'),
            'lines' => array_values($this->validated('lines', [])),
        ];
    }
}
