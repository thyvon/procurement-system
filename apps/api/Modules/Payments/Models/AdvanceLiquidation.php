<?php

namespace Modules\Payments\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Payments\Database\Factories\AdvanceLiquidationFactory;

#[Fillable([
    'entity_id',
    'code',
    'status',
    'cash_request_id',
    'liquidation_date',
    'exchange_rate',
    'campus',
    'division',
    'department',
    'total_actual_expense_usd',
    'total_cash_advance_usd',
    'remaining_cash_usd',
    'created_by',
    'updated_by',
])]
class AdvanceLiquidation extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_RETURNED,
        self::STATUS_CANCELLED,
    ];

    protected static function newFactory(): AdvanceLiquidationFactory
    {
        return AdvanceLiquidationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'liquidation_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'total_actual_expense_usd' => 'decimal:2',
            'total_cash_advance_usd' => 'decimal:2',
            'remaining_cash_usd' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<AdvanceLiquidationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(AdvanceLiquidationLine::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<CashRequest, $this>
     */
    public function cashRequest(): BelongsTo
    {
        return $this->belongsTo(CashRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
