<?php

namespace Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'division_ids' => ['required', 'array', 'min:1'],
            'division_ids.*' => ['string', 'exists:divisions,id'],
        ];
    }
}
