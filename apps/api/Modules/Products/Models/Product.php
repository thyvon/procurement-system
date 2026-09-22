<?php

namespace Modules\Products\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[Fillable([
    'code',
    'name',
    'name_km',
    'description',
    'product_type',
    'product_category_id',
    'product_group_id',
    'brand_id',
    'uom_id',
    'sub_unit_id',
    'purchase_price',
    'sub_unit_purchase_price',
    'image_url',
    'is_active',
    'created_by',
    'updated_by',
])]
class Product extends Model
{
    use BelongsToEntity, HasUlids, Searchable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'product_type' => 'string',
            'purchase_price' => 'decimal:4',
            'sub_unit_purchase_price' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function subUnit(): BelongsTo
    {
        return $this->belongsTo(UomSubUnit::class, 'sub_unit_id');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Templates whose options define this product's variation matrix axes.
     *
     * @return BelongsToMany<VariationTemplate, $this>
     */
    public function variationTemplates(): BelongsToMany
    {
        return $this->belongsToMany(
            VariationTemplate::class,
            'product_variation_template',
            'product_id',
            'variation_template_id',
        );
    }

    /**
     * Meilisearch index payload — entity_id is a filter attribute so the
     * repository can scope searches to the caller's entity.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'entity_id' => $this->entity_id,
            'code' => $this->code,
            'name' => $this->name,
            'name_km' => $this->name_km,
            'is_active' => $this->is_active,
        ];
    }

    public function searchableAs(): string
    {
        // Respect scout.prefix so dev/test/prod indexes never collide.
        return config('scout.prefix').'products';
    }
}
