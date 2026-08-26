<?php

namespace Modules\Products\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductVariant;

class VariationService
{
    /**
     * Merge existing single-type products into a variable product family.
     * Each source becomes a variant of the parent; sources themselves are
     * soft-deleted. Mirrors the reference app's merge-variation flow.
     *
     * @param  array<int, string>  $templateIds  variation template ids used
     * @param  array<int, array{productId: string, values: array<string, string>}>  $assignments
     */
    public function merge(User $actor, string $parentProductId, array $templateIds, array $assignments): Product
    {
        return DB::transaction(function () use ($actor, $parentProductId, $templateIds, $assignments) {
            /** @var Product|null $parent */
            $parent = Product::query()->find($parentProductId);

            if ($parent === null) {
                throw new \InvalidArgumentException('Parent product not found.');
            }

            if ($templateIds === []) {
                throw new \InvalidArgumentException('Select at least one variation template.');
            }

            if ($assignments === []) {
                throw new \InvalidArgumentException('At least one product assignment is required.');
            }

            $seen = [];
            foreach ($assignments as $row) {
                $pid = (string) ($row['productId'] ?? '');
                if ($pid === '') {
                    throw new \InvalidArgumentException('Assignment is missing a product.');
                }
                if (isset($seen[$pid])) {
                    throw new \InvalidArgumentException('Duplicate product in assignments.');
                }
                $seen[$pid] = true;
            }

            if (! isset($seen[$parent->getKey()])) {
                throw new \InvalidArgumentException('The parent product must also be assigned variation values.');
            }

            foreach ($assignments as $row) {
                $pid = (string) $row['productId'];

                if ($pid === $parent->getKey()) {
                    continue;
                }

                /** @var Product|null $source */
                $source = Product::query()->find($pid);

                if ($source === null) {
                    throw new \InvalidArgumentException("Product {$pid} not found.");
                }
                if ($source->product_type !== 'single') {
                    throw new \InvalidArgumentException("\"{$source->name}\" is not a single product and cannot be merged.");
                }

                $this->createVariantFromSource($parent, $source, (array) ($row['values'] ?? []));
                $source->delete();
            }

            // The parent keeps its own default variant, matching the reference flow.
            $parent = $parent->refresh();
            $this->createVariantFromSource(
                $parent,
                $parent,
                (array) collect($assignments)->firstWhere('productId', $parent->getKey())['values'] ?? [],
            );

            $parent->forceFill([
                'product_type' => 'variable',
                'updated_by' => $actor->getKey(),
            ])->save();

            return $parent->load('variants');
        });
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
            'option_values' => $optionValues,
            'purchase_price' => $source->purchase_price,
            'sub_unit_purchase_price' => $source->sub_unit_purchase_price,
            'image_url' => $source->image_url,
            'is_active' => $source->is_active,
        ]);
        $parent->variants()->save($variant);
    }
}
