<?php

namespace Modules\Products\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Products\Models\Brand;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductCategory;
use Modules\Products\Models\Uom;

class ProductImportService
{
    /**
     * Validate spreadsheet rows and insert every valid one inside one
     * transaction. Row-level problems are reported, not fatal — matching
     * the reference app's "skip bad lines, report them" behaviour.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(User $actor, array $rows): array
    {
        $errors = [];
        $imported = 0;
        $seenCodes = [];

        // Lookup caches so repeated codes resolve once.
        $categories = ProductCategory::query()
            ->where('entity_id', $actor->entity_id)
            ->get(['id', 'code'])
            ->keyBy(fn ($c) => Str::lower($c->code));
        $uoms = Uom::query()
            ->where('entity_id', $actor->entity_id)
            ->get(['id', 'short_name'])
            ->keyBy(fn ($u) => Str::lower($u->short_name));
        $brands = Brand::query()
            ->where('entity_id', $actor->entity_id)
            ->get(['id', 'name'])
            ->keyBy(fn ($b) => Str::lower($b->name));

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $line = $index + 2; // header is line 1

                $code = trim((string) ($row['code'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));

                if ($code === '' || $name === '') {
                    $errors[] = ['row' => $line, 'message' => 'Code and name are required.'];

                    continue;
                }

                $key = Str::lower($code);
                if (isset($seenCodes[$key])) {
                    $errors[] = ['row' => $line, 'message' => "Duplicate code \"{$code}\" in this file."];

                    continue;
                }

                if (Product::query()->where('entity_id', $actor->entity_id)->where('code', $code)->exists()) {
                    $errors[] = ['row' => $line, 'message' => "Code \"{$code}\" already exists."];

                    continue;
                }

                $categoryId = null;
                $categoryCode = trim((string) ($row['category_code'] ?? ''));
                if ($categoryCode !== '') {
                    $category = $categories->get(Str::lower($categoryCode));
                    if ($category === null) {
                        $errors[] = ['row' => $line, 'message' => "Category \"{$categoryCode}\" not found."];

                        continue;
                    }
                    $categoryId = $category->getKey();
                }

                $uomId = null;
                $subUnitId = null;
                $uomShort = trim((string) ($row['uom_short_name'] ?? ''));
                if ($uomShort !== '') {
                    $uom = $uoms->get(Str::lower($uomShort));
                    if ($uom === null) {
                        $errors[] = ['row' => $line, 'message' => "UoM \"{$uomShort}\" not found."];

                        continue;
                    }
                    $uomId = $uom->getKey();
                }

                $brandId = null;
                $brandName = trim((string) ($row['brand'] ?? ''));
                if ($brandName !== '') {
                    $brand = $brands->get(Str::lower($brandName));
                    if ($brand === null) {
                        $errors[] = ['row' => $line, 'message' => "Brand \"{$brandName}\" not found."];

                        continue;
                    }
                    $brandId = $brand->getKey();
                }

                $price = $row['purchase_price'] ?? null;
                if ($price !== null && $price !== '' && ! is_numeric($price)) {
                    $errors[] = ['row' => $line, 'message' => "Purchase price \"{$price}\" is not numeric."];

                    continue;
                }

                $status = Str::lower(trim((string) ($row['status'] ?? 'active')));

                Product::create([
                    'entity_id' => $actor->entity_id,
                    'code' => $code,
                    'name' => $name,
                    'name_km' => trim((string) ($row['name_km'] ?? '')) ?: null,
                    'description' => trim((string) ($row['description'] ?? '')) ?: null,
                    'purchase_price' => ($price !== null && $price !== '') ? (float) $price : null,
                    'product_category_id' => $categoryId,
                    'uom_id' => $uomId,
                    'sub_unit_id' => $subUnitId,
                    'brand_id' => $brandId,
                    'is_active' => $status !== 'inactive',
                    'created_by' => $actor->getKey(),
                    'updated_by' => $actor->getKey(),
                ]);

                $seenCodes[$key] = true;
                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'imported' => $imported,
            'skipped' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function templateRows(): array
    {
        return [
            'code,name,name_km,category_code,brand,uom_short_name,purchase_price,description,status',
            'STF-001,A4 Copy Paper 70gsm,ក្រដាស់តពុន A4,OFF,,PCS,3.50,Example product,active',
            'STF-002,Ballpoint Pen Blue,ប៉ិចសាច់ប្រាក់,OFF,,PCS,0.75,,active',
        ];
    }
}
