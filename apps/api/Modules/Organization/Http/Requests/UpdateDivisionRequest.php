<?php

namespace Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Organization\Models\Division;

class UpdateDivisionRequest extends FormRequest
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
        $divisionId = $this->route('division') instanceof Division
            ? $this->route('division')->getKey()
            : $this->route('division');

        return [
            'name' => ['sometimes', 'string', 'max:64'],
            'short_code' => [
                'sometimes',
                'string',
                'max:16',
                Rule::unique('divisions', 'short_code')
                    ->ignore($divisionId)
                    ->where('entity_id', $entityId)
                    ->whereNull('deleted_at'),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['string', 'exists:departments,id'],
        ];
    }
}
