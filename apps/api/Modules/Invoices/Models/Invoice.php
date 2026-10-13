<?php

namespace Modules\Invoices\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Invoices\Database\Factories\InvoiceFactory;

#[Fillable([
    'entity_id',
    'code',
    'invoice_number',
    'status',
    'supplier_name',
    'supplier_code',
    'invoice_date',
    'due_date',
    'currency',
    'exchange_rate',
    'subtotal',
    'vat_amount',
    'discount_amount',
    'total',
    'grand_total_usd',
    'notes',
    'paid_at',
    'created_by',
    'updated_by',
])]
class Invoice extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RECORDED = 'recorded';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RECORDED,
        self::STATUS_CANCELLED,
    ];

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'grand_total_usd' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
