<?php

namespace Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Products\Http\Requests\Concerns\ValidatesProductVariation;

class UpdateProductRequest extends FormRequest
{
    use ValidatesProductVariation;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $productId = $this->route('product');
        $entityId = $this->user()?->entity_id;

        return [
            'code' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('products', 'code')
                    ->where('entity_id', $entityId)
                    ->ignore($productId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
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
            ...$this->variationRules(),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateProductPricePair($validator);
            $this->validateVariationPayload($validator);
        });
    }

    /**
     * Map API field names to model columns (no variation arrays).
     *
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        return collect($this->validated())
            ->except(['template_ids', 'variants'])
            ->mapWithKeys(fn ($value, $key) => [$this->columnFor($key) => $value])
            ->all();
    }

    private function columnFor(string $field): string
    {
        return match ($field) {
            'category_id' => 'product_category_id',
            'group_id' => 'product_group_id',
            default => $field,
        };
    }
}
