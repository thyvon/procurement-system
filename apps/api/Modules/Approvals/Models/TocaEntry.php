<?php

namespace Modules\Approvals\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'entity_id',
    'user_id',
    'subject_type',
    'min_amount',
    'max_amount',
    'created_by',
    'updated_by',
])]
class TocaEntry extends Model
{
    use BelongsToEntity, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coversAmount(float $amount): bool
    {
        if ($amount < (float) $this->min_amount) {
            return false;
        }

        return $this->max_amount === null || $amount <= (float) $this->max_amount;
    }
}
