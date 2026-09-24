<?php

namespace Modules\EPurchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchEPurchaseVendorsRequest extends FormRequest
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
            'term' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
