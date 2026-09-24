<?php

namespace Modules\PurchaseOrders\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'entity_id',
    'evaluation_id',
    'evaluation_quotation_id',
    'evaluation_item_id',
    'brand',
    'unit_cost',
    'line_total',
    'is_selected',
])]
class EvaluationQuotationItem extends Model
{
    use BelongsToEntity, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:4',
            'line_total' => 'decimal:4',
            'is_selected' => 'boolean',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(EvaluationQuotation::class, 'evaluation_quotation_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EvaluationItem::class, 'evaluation_item_id');
    }
}
