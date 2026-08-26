<?php

namespace Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Organization\Models\Entity;

class UpdateEntityRequest extends FormRequest
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
        $entityId = $this->route('entity') instanceof Entity
            ? $this->route('entity')->getKey()
            : $this->route('entity');

        return [
            'code' => ['sometimes', 'string', 'max:32', 'alpha_dash', Rule::unique('entities', 'code')->ignore($entityId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'max:64', Rule::in(timezone_identifiers_list())],
            'locale' => ['sometimes', 'string', 'max:12'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
