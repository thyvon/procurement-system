<?php

namespace Modules\Approvals\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'entity_id',
    'approval_setting_id',
    'code',
    'name',
    'min_amount',
    'max_amount',
    'is_active',
    'created_by',
    'updated_by',
])]
class ApprovalFlow extends Model
{
    use BelongsToEntity, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ApprovalSetting, $this>
     */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(ApprovalSetting::class, 'approval_setting_id');
    }

    /**
     * @return HasMany<ApprovalStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('position');
    }

    public function coversAmount(float $amount): bool
    {
        if ($amount < (float) $this->min_amount) {
            return false;
        }

        return $this->max_amount === null || $amount <= (float) $this->max_amount;
    }
}
