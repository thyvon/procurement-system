<?php

namespace Modules\PurchaseOrders\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'entity_id',
    'code',
    'status',
    'recommendation_basis',
    'awarded_total',
    'created_by',
    'updated_by',
])]
class Evaluation extends Model
{
    use BelongsToEntity, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'awarded_total' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<EvaluationQuotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(EvaluationQuotation::class)->orderBy('position');
    }

    /**
     * @return HasMany<EvaluationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EvaluationItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<EvaluationQuotationItem, $this>
     */
    public function quotationItems(): HasMany
    {
        return $this->hasMany(EvaluationQuotationItem::class);
    }
}
