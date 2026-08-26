<?php

namespace Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntityRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('entities', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:64', Rule::in(timezone_identifiers_list())],
            'locale' => ['required', 'string', 'max:12'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
