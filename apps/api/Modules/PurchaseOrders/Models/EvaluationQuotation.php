<?php

namespace Modules\PurchaseOrders\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'entity_id',
    'evaluation_id',
    'supplier_code',
    'supplier_name',
    'supplier_phone',
    'supplier_address',
    'discount',
    'vat',
    'subtotal',
    'grand_total',
    'price',
    'quality',
    'lead_time',
    'warranty',
    'payment_terms',
    'position',
])]
class EvaluationQuotation extends Model
{
    use BelongsToEntity, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'discount' => 'decimal:2',
            'vat' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /**
     * Matrix cells (brand/unit cost/selection) for this quotation.
     *
     * @return HasMany<EvaluationQuotationItem, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(EvaluationQuotationItem::class);
    }
}
