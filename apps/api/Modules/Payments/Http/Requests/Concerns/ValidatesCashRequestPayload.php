<?php

namespace Modules\Payments\Http\Requests\Concerns;

use App\Support\Currency;
use Illuminate\Validation\Rule;
use Modules\Payments\Models\CashRequest;

/**
 * Shared cash request payload rules for Store/Update cash request requests. The line
 * list is always submitted whole (full-replace semantics); money columns
 * (total_usd, line amount_usd) and `code`/`status` are server-owned and never accepted.
 */
trait ValidatesCashRequestPayload
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function cashRequestRules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(CashRequest::TYPES)],
            'campus' => ['required', 'string', 'max:64'],
            'division' => ['required', 'string', 'max:64'],
            'department' => ['required', 'string', 'max:64'],
            'request_date' => ['required', 'date'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'mode_of_payment' => ['nullable', 'string', 'max:32'],
            'payment_to' => ['nullable', 'string', 'max:128'],
            'bank_account_no' => ['nullable', 'string', 'max:64'],
            'bank_name' => ['nullable', 'string', 'max:128'],
            'advance_request_for' => ['nullable', 'required_if:type,advance', 'string', 'max:128'],
            'remark' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.currency' => ['sometimes', 'string', Rule::in(Currency::CODES)],
            'lines.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Column-bound payload for the service (lines array intact).
     *
     * @return array<string, mixed>
     */
    public function cashRequestData(): array
    {
        return [
            'type' => $this->validated('type'),
            'campus' => $this->validated('campus'),
            'division' => $this->validated('division'),
            'department' => $this->validated('department'),
            'request_date' => $this->validated('request_date'),
            'exchange_rate' => $this->validated('exchange_rate'),
            'mode_of_payment' => $this->validated('mode_of_payment'),
            'payment_to' => $this->validated('payment_to'),
            'bank_account_no' => $this->validated('bank_account_no'),
            'bank_name' => $this->validated('bank_name'),
            'advance_request_for' => $this->validated('advance_request_for'),
            'remark' => $this->validated('remark'),
            'lines' => array_values($this->validated('lines', [])),
        ];
    }
}
