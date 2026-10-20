<?php

namespace Modules\Payments\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Payments\Database\Factories\AdvanceLiquidationLineFactory;

#[Fillable([
    'entity_id',
    'advance_liquidation_id',
    'position',
    'description',
    'currency',
    'amount',
    'amount_usd',
])]
class AdvanceLiquidationLine extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): AdvanceLiquidationLineFactory
    {
        return AdvanceLiquidationLineFactory::new();
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_usd' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<AdvanceLiquidation, $this>
     */
    public function advanceLiquidation(): BelongsTo
    {
        return $this->belongsTo(AdvanceLiquidation::class);
    }
}
