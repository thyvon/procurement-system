<?php

namespace Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Organization\Models\Department;

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
        $entityId = $this->user()?->entity_id;
        $departmentId = $this->route('department') instanceof Department
            ? $this->route('department')->getKey()
            : $this->route('department');

        return [
            'name' => ['sometimes', 'string', 'max:64'],
            'short_code' => [
                'sometimes',
                'string',
                'max:16',
                Rule::unique('departments', 'short_code')
                    ->ignore($departmentId)
                    ->where('entity_id', $entityId)
                    ->whereNull('deleted_at'),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'division_ids' => ['sometimes', 'array'],
            'division_ids.*' => ['string', 'exists:divisions,id'],
        ];
    }
}
