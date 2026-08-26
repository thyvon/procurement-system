<?php

namespace Modules\Products\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'product_id',
    'code',
    'name',
    'option_values',
    'purchase_price',
    'sub_unit_purchase_price',
    'image_url',
    'is_active',
])]
class ProductVariant extends Model
{
    use BelongsToEntity, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'option_values' => 'array',
            'purchase_price' => 'decimal:4',
            'sub_unit_purchase_price' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
