<?php

namespace Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('products', 'code')->where('entity_id', $entityId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'name_km' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'product_type' => ['sometimes', Rule::in(['single', 'variable'])],
            'category_id' => ['nullable', Rule::exists('product_categories', 'id')],
            'group_id' => ['nullable', Rule::exists('product_groups', 'id')],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'uom_id' => ['nullable', Rule::exists('uoms', 'id')],
            'sub_unit_id' => ['nullable', Rule::exists('uom_sub_units', 'id')],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sub_unit_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
