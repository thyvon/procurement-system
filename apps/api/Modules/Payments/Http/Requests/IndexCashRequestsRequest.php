<?php

namespace Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payments\Models\CashRequest;

class IndexCashRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in(CashRequest::STATUSES)],
            'type' => ['sometimes', 'nullable', Rule::in(CashRequest::TYPES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
