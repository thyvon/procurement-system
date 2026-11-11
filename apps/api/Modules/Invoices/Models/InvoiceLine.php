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
    'source_type',
    'source_header_id',
    'source_line_id',
    'source_ref',
    'item_code',
])]
class InvoiceLine extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    /**
     * The ePurchase document a line's items came from. The reference is a
     * client-supplied snapshot (the source system is an external proxy with
     * integer ids no local FK could target) kept for traceability only.
     *
     * @var array<int, string>
     */
    public const SOURCE_TYPES = ['pr', 'po'];

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
            'source_header_id' => 'integer',
            'source_line_id' => 'integer',
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
