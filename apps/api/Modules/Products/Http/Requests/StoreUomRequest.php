<?php

namespace Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUomRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:16'],
            'is_active' => ['sometimes', 'boolean'],
            'sub_units' => ['sometimes', 'array'],
            'sub_units.*.name' => ['required_with:sub_units', 'string', 'max:255'],
            'sub_units.*.short_name' => ['required_with:sub_units', 'string', 'max:16'],
            'sub_units.*.conversion_factor' => ['required_with:sub_units', 'numeric', 'gt:0'],
        ];
    }
}
