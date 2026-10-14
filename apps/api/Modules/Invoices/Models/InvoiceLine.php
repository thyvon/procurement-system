<?php

namespace Modules\Invoices\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Invoices\Database\Factories\InvoiceLineFactory;

#[Fillable([
    'entity_id',
    'invoice_id',
    'description',
    'uom',
    'campus',
    'division',
    'department',
    'location',
    'purpose',
    'qty',
    'unit_price',
    'discount',
    'line_total',
    'grand_total_usd',
    'position',
])]
class InvoiceLine extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): InvoiceLineFactory
    {
        return InvoiceLineFactory::new();
    }

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'grand_total_usd' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
