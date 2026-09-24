<?php

namespace Modules\Products\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Modules\Products\Models\Product;
use Modules\Products\Models\VariationTemplateOption;

/**
 * Shared variation payload rules for Store/Update product requests.
 * option_values is a map of template id => option id (rename-safe).
 */
trait ValidatesProductVariation
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function variationRules(): array
    {
        $entityId = $this->user()?->entity_id;

        return [
            'template_ids' => ['sometimes', 'array'],
            'template_ids.*' => [Rule::exists('variation_templates', 'id')->where('entity_id', $entityId)],
            'variants' => ['sometimes', 'array'],
            'variants.*.id' => ['nullable', 'string', 'max:26'],
            'variants.*.code' => ['nullable', 'string', 'max:64'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.description' => ['nullable', 'string'],
            'variants.*.option_values' => ['required_with:variants', 'array', 'min:1'],
            'variants.*.option_values.*' => ['string', 'max:26'],
            'variants.*.sub_unit_id' => ['nullable', Rule::exists('uom_sub_units', 'id')],
            'variants.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sub_unit_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function templateIds(): array
    {
        /** @var array<int, string> */
        return array_values($this->validated('template_ids', []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function variantRows(): array
    {
        /** @var array<int, array<string, mixed>> */
        return array_values($this->validated('variants', []));
    }

    /**
     * Whether this request should rewrite templates + variants.
     * Partial updates that omit variation keys leave the matrix untouched.
     */
    public function shouldSyncVariation(): bool
    {
        return $this->has('template_ids')
            || $this->has('variants')
            || $this->has('product_type');
    }

    protected function effectiveProductType(): string
    {
        if ($this->filled('product_type')) {
            return (string) $this->input('product_type');
        }

        $product = $this->route('product');
        if ($product instanceof Product) {
            return $product->product_type;
        }

        return 'single';
    }

    protected function validateProductPricePair(Validator $validator): void
    {
        $product = $this->route('product') instanceof Product
            ? $this->route('product')
            : null;

        $all = $this->all();
        $subUnitId = array_key_exists('sub_unit_id', $all)
            ? $all['sub_unit_id']
            : $product?->sub_unit_id;
        $subPrice = array_key_exists('sub_unit_purchase_price', $all)
            ? $all['sub_unit_purchase_price']
            : $product?->sub_unit_purchase_price;
        $basePrice = array_key_exists('purchase_price', $all)
            ? $all['purchase_price']
            : $product?->purchase_price;

        if ($subPrice !== null && $subUnitId === null) {
            $validator->errors()->add(
                'sub_unit_purchase_price',
                'A sub-unit purchase price requires a sub-unit.',
            );
        }

        if ($subPrice !== null && $basePrice !== null && (float) $subPrice > (float) $basePrice) {
            $validator->errors()->add(
                'sub_unit_purchase_price',
                'Sub-unit purchase price cannot exceed the purchase price.',
            );
        }
    }

    protected function validateVariationPayload(Validator $validator): void
    {
        if (! $this->shouldSyncVariation()) {
            return;
        }

        $type = $this->effectiveProductType();

        if ($type !== 'variable') {
            return;
        }

        $templateIds = $this->templateIds();
        if ($templateIds === []) {
            $validator->errors()->add(
                'template_ids',
                'At least one variation template is required for a variable product.',
            );

            return;
        }

        $variants = $this->variantRows();
        if ($variants === []) {
            $validator->errors()->add(
                'variants',
                'At least one variant is required for a variable product.',
            );

            return;
        }

        $templateIdSet = array_flip(array_map('strval', $templateIds));
        $expectedKeys = array_keys($templateIdSet);
        sort($expectedKeys);

        $optionTemplateMap = VariationTemplateOption::query()
            ->whereIn('variation_template_id', $templateIds)
            ->pluck('variation_template_id', 'id');

        $seenCombos = [];
        $seenCodes = [];
        $seenNames = [];

        foreach ($variants as $index => $row) {
            /** @var array<string, mixed> $row */
            $optionValues = is_array($row['option_values'] ?? null) ? $row['option_values'] : [];

            if ($optionValues === []) {
                $validator->errors()->add(
                    "variants.{$index}.option_values",
                    'Each variant must select one option per template.',
                );

                continue;
            }

            $keys = array_map('strval', array_keys($optionValues));
            sort($keys);

            if ($keys !== $expectedKeys) {
                $validator->errors()->add(
                    "variants.{$index}.option_values",
                    'Each variant must select exactly one option for every selected template.',
                );
            }

            foreach ($optionValues as $templateId => $optionId) {
                $templateKey = (string) $templateId;
                if (! isset($templateIdSet[$templateKey])) {
                    $validator->errors()->add(
                        "variants.{$index}.option_values",
                        'A selected template is not in template_ids.',
                    );

                    continue;
                }

                $owner = $optionTemplateMap->get((string) $optionId);
                if ($owner === null || (string) $owner !== $templateKey) {
                    $validator->errors()->add(
                        "variants.{$index}.option_values",
                        'One or more selected options do not belong to their template.',
                    );
                }
            }

            $comboKey = static::comboKeyFor($optionValues);
            if (isset($seenCombos[$comboKey])) {
                $validator->errors()->add(
                    "variants.{$index}.option_values",
                    'Duplicate variation combination.',
                );
            }
            $seenCombos[$comboKey] = true;

            $base = $row['purchase_price'] ?? null;
            $sub = $row['sub_unit_purchase_price'] ?? null;
            $subUnit = $row['sub_unit_id'] ?? null;

            if ($sub !== null && $subUnit === null) {
                $validator->errors()->add(
                    "variants.{$index}.sub_unit_purchase_price",
                    'A sub-unit purchase price requires a sub-unit.',
                );
            }

            if ($sub !== null && $base !== null && (float) $sub > (float) $base) {
                $validator->errors()->add(
                    "variants.{$index}.sub_unit_purchase_price",
                    'Sub-unit purchase price cannot exceed the purchase price.',
                );
            }

            $code = trim((string) ($row['code'] ?? ''));
            if ($code !== '') {
                if (isset($seenCodes[$code])) {
                    $validator->errors()->add(
                        "variants.{$index}.code",
                        'Variant codes must be unique.',
                    );
                }
                $seenCodes[$code] = true;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                if (isset($seenNames[$name])) {
                    $validator->errors()->add(
                        "variants.{$index}.name",
                        'Variant names must be unique.',
                    );
                }
                $seenNames[$name] = true;
            }
        }
    }

    /**
     * Stable identity for a combination of template => option selections.
     *
     * @param  array<string|int, string|int>  $optionValues
     */
    public static function comboKeyFor(array $optionValues): string
    {
        ksort($optionValues);

        return collect($optionValues)
            ->map(fn ($optionId, $templateId) => $templateId.':'.$optionId)
            ->implode('|');
    }
}
