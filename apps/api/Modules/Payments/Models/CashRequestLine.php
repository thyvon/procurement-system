<?php

namespace Modules\Payments\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Payments\Database\Factories\CashRequestLineFactory;

#[Fillable([
    'entity_id',
    'cash_request_id',
    'position',
    'description',
    'currency',
    'amount',
    'amount_usd',
])]
class CashRequestLine extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): CashRequestLineFactory
    {
        return CashRequestLineFactory::new();
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_usd' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CashRequest, $this>
     */
    public function cashRequest(): BelongsTo
    {
        return $this->belongsTo(CashRequest::class);
    }
}
