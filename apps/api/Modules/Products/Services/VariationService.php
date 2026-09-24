<?php

namespace Modules\Products\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductVariant;
use Modules\Products\Models\VariationTemplate;

class VariationService
{
    /**
     * Merge existing single-type products into a variable product family.
     * Each source becomes a variant of the parent; sources themselves are
     * soft-deleted. option_values are stored as {templateId: optionId}.
     *
     * @param  array<int, string>  $templateIds  variation template ids used
     * @param  array<int, array{productId: string, values: array<string, string>}>  $assignments  template-name => option-value maps
     */
    public function merge(User $actor, string $parentProductId, array $templateIds, array $assignments): Product
    {
        return DB::transaction(function () use ($actor, $parentProductId, $templateIds, $assignments) {
            /** @var Product|null $parent */
            $parent = Product::query()->find($parentProductId);

            if ($parent === null) {
                throw ValidationException::withMessages([
                    'parentId' => 'Parent product not found.',
                ]);
            }

            if ($templateIds === []) {
                throw ValidationException::withMessages([
                    'templateIds' => 'Select at least one variation template.',
                ]);
            }

            if ($assignments === []) {
                throw ValidationException::withMessages([
                    'assignments' => 'At least one product assignment is required.',
                ]);
            }

            $templates = VariationTemplate::with('options')
                ->whereIn('id', $templateIds)
                ->get();

            if ($templates->count() !== count(array_unique($templateIds))) {
                throw ValidationException::withMessages([
                    'templateIds' => 'One or more variation templates were not found.',
                ]);
            }

            $seen = [];
            foreach ($assignments as $row) {
                $pid = (string) ($row['productId'] ?? '');
                if ($pid === '') {
                    throw ValidationException::withMessages([
                        'assignments' => 'Assignment is missing a product.',
                    ]);
                }
                if (isset($seen[$pid])) {
                    throw ValidationException::withMessages([
                        'assignments' => 'Duplicate product in assignments.',
                    ]);
                }
                $seen[$pid] = true;
            }

            if (! isset($seen[$parent->getKey()])) {
                throw ValidationException::withMessages([
                    'assignments' => 'The parent product must also be assigned variation values.',
                ]);
            }

            $resolvedByPid = [];
            $comboKeys = [];

            foreach ($assignments as $row) {
                $pid = (string) $row['productId'];
                $resolved = $this->resolveOptionValues(
                    $templates,
                    (array) ($row['values'] ?? []),
                );

                $this->assertCompleteCombo($resolved, $templateIds, $pid);

                $comboKey = $this->comboKey($resolved);
                if (isset($comboKeys[$comboKey])) {
                    throw ValidationException::withMessages([
                        'assignments' => 'Duplicate variation combination in assignments.',
                    ]);
                }
                $comboKeys[$comboKey] = true;

                $resolvedByPid[$pid] = $resolved;
            }

            foreach ($assignments as $row) {
                $pid = (string) $row['productId'];

                if ($pid === $parent->getKey()) {
                    continue;
                }

                /** @var Product|null $source */
                $source = Product::query()->find($pid);

                if ($source === null) {
                    throw ValidationException::withMessages([
                        'assignments' => "Product {$pid} not found.",
                    ]);
                }
                if ($source->product_type !== 'single') {
                    throw ValidationException::withMessages([
                        'assignments' => "\"{$source->name}\" is not a single product and cannot be merged.",
                    ]);
                }

                $this->createVariantFromSource($parent, $source, $resolvedByPid[$pid]);
                $source->delete();
            }

            // The parent keeps its own default variant, matching the reference flow.
            $parent = $parent->refresh();
            $this->createVariantFromSource(
                $parent,
                $parent,
                $resolvedByPid[$parent->getKey()],
            );

            $parent->forceFill([
                'product_type' => 'variable',
                'updated_by' => $actor->getKey(),
            ])->save();

            $parent->variationTemplates()->sync($templateIds);

            return $parent->load(['variants', 'variationTemplates']);
        });
    }

    /**
     * Rewrite templates + variants to match the product form payload.
     * Callers must already hold a transaction when composing with product writes.
     *
     * @param  array<int, string>  $templateIds
     * @param  array<int, array<string, mixed>>  $variants
     */
    public function applyToProduct(Product $product, array $templateIds, array $variants): Product
    {
        return DB::transaction(function () use ($product, $templateIds, $variants) {
            if ($product->product_type !== 'variable') {
                $product->variationTemplates()->sync([]);
                $product->variants()->where('product_id', $product->getKey())->forceDelete();

                return $product->load(['variants', 'variationTemplates']);
            }

            $product->variationTemplates()->sync($templateIds);
            $this->syncVariants($product, $templateIds, $variants);

            return $product->load(['variants', 'variationTemplates']);
        });
    }

    /**
     * @param  array<int, string>  $templateIds
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $templateIds, array $variants): void
    {
        $templates = VariationTemplate::with('options')
            ->whereIn('id', $templateIds)
            ->get()
            ->keyBy('id');

        $existing = $product->variants()->get()->keyBy('id');
        $keepIds = [];
        $seenCombos = [];
        $seenCodes = [];
        $seenNames = [];

        foreach ($variants as $row) {
            $optionValues = $this->normalizeOptionValues(is_array($row['option_values'] ?? null)
                ? $row['option_values']
                : []);

            $comboKey = $this->comboKey($optionValues);
            if (isset($seenCombos[$comboKey])) {
                throw ValidationException::withMessages([
                    'variants' => 'Duplicate variation combination.',
                ]);
            }
            $seenCombos[$comboKey] = true;

            $code = trim((string) ($row['code'] ?? ''));
            $code = $code !== '' ? $code : null;
            if ($code !== null) {
                if (isset($seenCodes[$code])) {
                    throw ValidationException::withMessages([
                        'variants' => 'Variant codes must be unique.',
                    ]);
                }
                $seenCodes[$code] = true;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = $this->defaultVariantName($product, $optionValues, $templates);
            }
            $name = $this->uniqueVariantName($name, $seenNames);
            $seenNames[$name] = true;

            $attrs = [
                'entity_id' => $product->entity_id,
                'code' => $code,
                'name' => $name,
                'description' => $row['description'] ?? null,
                'option_values' => $optionValues,
                'sub_unit_id' => $row['sub_unit_id'] ?? null,
                'purchase_price' => $row['purchase_price'] ?? null,
                'sub_unit_purchase_price' => $row['sub_unit_purchase_price'] ?? null,
                'is_active' => $row['is_active'] ?? true,
            ];

            $variantId = $row['id'] ?? null;
            if ($variantId !== null && $existing->has((string) $variantId)) {
                /** @var ProductVariant $variant */
                $variant = $existing->get((string) $variantId);
                $variant->fill($attrs)->save();
                $keepIds[] = $variant->getKey();

                continue;
            }

            $variant = new ProductVariant($attrs);
            $product->variants()->save($variant);
            $keepIds[] = $variant->getKey();
        }

        $stale = $product->variants();
        if ($keepIds !== []) {
            $stale->whereNotIn('id', $keepIds);
        }
        // forceDelete frees unique(name/code) slots so re-adding a combo works.
        $stale->forceDelete();
    }

    /**
     * Resolve merge payload {templateName: optionValue} to {templateId: optionId}.
     *
     * @param  Collection<int, VariationTemplate>  $templates
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function resolveOptionValues($templates, array $values): array
    {
        $byName = $templates->keyBy('name');
        $resolved = [];

        foreach ($values as $templateName => $optionValue) {
            $template = $byName->get((string) $templateName);

            if ($template === null) {
                throw ValidationException::withMessages([
                    'assignments' => "Unknown variation template \"{$templateName}\".",
                ]);
            }

            $option = $template->options->firstWhere('value', $optionValue);

            if ($option === null) {
                throw ValidationException::withMessages([
                    'assignments' => "Unknown option \"{$optionValue}\" for template \"{$templateName}\".",
                ]);
            }

            $resolved[$template->getKey()] = $option->getKey();
        }

        return $resolved;
    }

    /**
     * @param  array<string, string>  $resolved
     * @param  array<int, string>  $templateIds
     */
    private function assertCompleteCombo(array $resolved, array $templateIds, string $pid): void
    {
        $keys = array_map('strval', array_keys($resolved));
        sort($keys);

        $expected = array_map('strval', $templateIds);
        sort($expected);

        if ($keys !== $expected) {
            throw ValidationException::withMessages([
                'assignments' => "Assignment for product {$pid} must select one option for every template.",
            ]);
        }
    }

    /**
     * @param  array<string, string>  $optionValues
     */
    private function comboKey(array $optionValues): string
    {
        ksort($optionValues);

        return collect($optionValues)
            ->map(fn ($optionId, $templateId) => $templateId.':'.$optionId)
            ->implode('|');
    }

    /**
     * @param  array<string|int, mixed>  $optionValues
     * @return array<string, string>
     */
    private function normalizeOptionValues(array $optionValues): array
    {
        $normalized = [];
        foreach ($optionValues as $templateId => $optionId) {
            $normalized[(string) $templateId] = (string) $optionId;
        }

        return $normalized;
    }

    /**
     * @param  Collection<int, VariationTemplate>  $templates
     * @param  array<string, string>  $optionValues
     */
    private function defaultVariantName(Product $product, array $optionValues, $templates): string
    {
        $labels = [];

        foreach ($optionValues as $templateId => $optionId) {
            $template = $templates->get($templateId);
            $option = $template?->options->firstWhere('id', $optionId);

            if ($option !== null) {
                $labels[] = $option->value;
            }
        }

        $suffix = $labels !== [] ? ' — '.implode(' / ', $labels) : '';

        return $product->name.$suffix;
    }

    /**
     * @param  array<string, true>  $seenNames
     */
    private function uniqueVariantName(string $name, array $seenNames): string
    {
        if (! isset($seenNames[$name])) {
            return $name;
        }

        $sequence = 2;
        while (isset($seenNames["{$name} ({$sequence})"])) {
            $sequence++;
        }

        return "{$name} ({$sequence})";
    }

    /**
     * @param  array<string, string>  $optionValues
     */
    private function createVariantFromSource(Product $parent, Product $source, array $optionValues): void
    {
        $variant = new ProductVariant([
            'entity_id' => $parent->entity_id,
            'code' => $source->code,
            'name' => $source->name,
            'description' => $source->description,
            'option_values' => $optionValues,
            'sub_unit_id' => $source->sub_unit_id,
            'purchase_price' => $source->purchase_price,
            'sub_unit_purchase_price' => $source->sub_unit_purchase_price,
            'image_url' => $source->image_url,
            'is_active' => $source->is_active,
        ]);
        $parent->variants()->save($variant);
    }
}
