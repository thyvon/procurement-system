<?php

namespace Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Organization\Models\Campus;

class UpdateCampusRequest extends FormRequest
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
        $campusId = $this->route('campus') instanceof Campus
            ? $this->route('campus')->getKey()
            : $this->route('campus');

        return [
            'name' => ['sometimes', 'string', 'max:64'],
            'short_code' => [
                'sometimes',
                'string',
                'max:16',
                Rule::unique('campuses', 'short_code')
                    ->ignore($campusId)
                    ->where('entity_id', $entityId)
                    ->whereNull('deleted_at'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
