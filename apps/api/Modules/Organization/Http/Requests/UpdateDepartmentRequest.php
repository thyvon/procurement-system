<?php

namespace Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'division_ids' => ['sometimes', 'array'],
            'division_ids.*' => ['string', 'exists:divisions,id'],
        ];
    }
}
