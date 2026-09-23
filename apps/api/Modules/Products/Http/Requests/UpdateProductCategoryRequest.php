<?php

namespace Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
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
        $categoryId = $this->route('category');

        return [
            'parent_id' => [
                'nullable',
                Rule::exists('product_categories', 'id')->whereNot('id', $categoryId),
            ],
            'short_code' => ['sometimes', 'string', 'max:16'],
            'name' => ['sometimes', 'string', 'max:255'],
            'name_km' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
