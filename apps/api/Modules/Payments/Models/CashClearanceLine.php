<?php

namespace Modules\Payments\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Payments\Database\Factories\CashClearanceLineFactory;

#[Fillable([
    'entity_id',
    'cash_clearance_id',
    'position',
    'invoice_date',
    'pr_ref',
    'purchaser',
    'description',
    'vendor',
    'vendor_phone',
    'campus',
    'division',
    'department',
    'qty',
    'uom',
    'unit_price_usd',
    'discount_usd',
    'vat_usd',
    'amount_usd',
])]
class CashClearanceLine extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): CashClearanceLineFactory
    {
        return CashClearanceLineFactory::new();
    }

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'qty' => 'decimal:4',
            'unit_price_usd' => 'decimal:2',
            'discount_usd' => 'decimal:2',
            'vat_usd' => 'decimal:2',
            'amount_usd' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CashClearance, $this>
     */
    public function cashClearance(): BelongsTo
    {
        return $this->belongsTo(CashClearance::class);
    }
}
