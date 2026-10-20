<?php

namespace Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $entityId = $this->user()?->entity_id;

        return [
            'name' => ['required', 'string', 'max:64'],
            'short_code' => [
                'nullable',
                'string',
                'max:16',
                Rule::unique('departments', 'short_code')
                    ->where('entity_id', $entityId)
                    ->whereNull('deleted_at'),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'division_ids' => ['required', 'array', 'min:1'],
            'division_ids.*' => ['string', 'exists:divisions,id'],
        ];
    }
}
