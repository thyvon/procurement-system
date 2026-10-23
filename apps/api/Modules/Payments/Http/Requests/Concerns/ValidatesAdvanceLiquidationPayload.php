<?php

namespace Modules\Payments\Http\Requests\Concerns;

use App\Support\Currency;
use Illuminate\Validation\Rule;

/**
 * Shared advance liquidation payload rules for Store/Update advance liquidation
 * requests. The line list is always submitted whole (full-replace semantics);
 * money columns (total_actual_expense_usd, total_cash_advance_usd,
 * remaining_cash_usd, line amount_usd) and `code`/`status` are server-owned
 * and never accepted.
 */
trait ValidatesAdvanceLiquidationPayload
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function advanceLiquidationRules(): array
    {
        return [
            'cash_request_id' => [
                'required',
                'string',
                Rule::exists('cash_requests', 'id')
                    ->where('entity_id', $this->user()?->entity_id)
                    ->whereNull('deleted_at'),
            ],
            'liquidation_date' => ['required', 'date'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'campus' => ['required', 'string', 'max:64'],
            'division' => ['required', 'string', 'max:64'],
            'department' => ['required', 'string', 'max:64'],
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
    public function advanceLiquidationData(): array
    {
        return [
            'cash_request_id' => $this->validated('cash_request_id'),
            'liquidation_date' => $this->validated('liquidation_date'),
            'exchange_rate' => $this->validated('exchange_rate'),
            'campus' => $this->validated('campus'),
            'division' => $this->validated('division'),
            'department' => $this->validated('department'),
            'remark' => $this->validated('remark'),
            'lines' => array_values($this->validated('lines', [])),
        ];
    }
}
