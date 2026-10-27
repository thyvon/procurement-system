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
use Modules\Invoices\Models\Invoice;
use Modules\Payments\Database\Factories\CashRequestFactory;

#[Fillable([
    'entity_id',
    'code',
    'type',
    'status',
    'campus',
    'division',
    'department',
    'request_date',
    'exchange_rate',
    'mode_of_payment',
    'payment_to',
    'bank_account_no',
    'bank_name',
    'advance_request_for',
    'total_usd',
    'remark',
    'created_by',
    'updated_by',
])]
class CashRequest extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    public const TYPE_PETTY_CASH = 'petty_cash';

    public const TYPE_ADVANCE = 'advance';

    public const TYPE_CASH = 'cash';

    public const TYPES = [
        self::TYPE_PETTY_CASH,
        self::TYPE_ADVANCE,
        self::TYPE_CASH,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CLEARED = 'cleared';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_RETURNED,
        self::STATUS_CANCELLED,
        self::STATUS_CLEARED,
    ];

    protected static function newFactory(): CashRequestFactory
    {
        return CashRequestFactory::new();
    }

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'total_usd' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<CashRequestLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CashRequestLine::class)->orderBy('position');
    }

    /**
     * Inverse of `invoices.cash_request_id`: every invoice drawing on this
     * request. Callers constrain status themselves (the budget counts
     * non-cancelled invoices only).
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'cash_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
