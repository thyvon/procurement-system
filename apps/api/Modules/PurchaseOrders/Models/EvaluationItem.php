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
    'item_code',
    'description',
    'qty',
    'uom',
    'position',
])]
class EvaluationItem extends Model
{
    use BelongsToEntity, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /**
     * @return HasMany<EvaluationQuotationItem, $this>
     */
    public function quotationItems(): HasMany
    {
        return $this->hasMany(EvaluationQuotationItem::class);
    }
}
