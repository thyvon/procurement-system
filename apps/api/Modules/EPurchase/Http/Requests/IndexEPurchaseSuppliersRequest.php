<?php

namespace Modules\EPurchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexEPurchaseSuppliersRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            // Upstream sends is_onboard as "1"/"0" — not Laravel's boolean
            // vocabulary, so `boolean` (which also rejects "true") would 422.
            'is_onboard' => ['sometimes', 'in:1'],
        ];
    }
}
