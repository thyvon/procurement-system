<?php

namespace Modules\Approvals\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'entity_id',
    'name',
    'subject_type',
    'step_key',
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
     * The users who hold this authority entry.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'toca_entry_user');
    }

    /**
     * The amount band covers `$amount` and the optional step scope admits
     * `$stepKey` — a row without a step scope admits every step.
     */
    public function coversAmount(float $amount, ?string $stepKey = null): bool
    {
        if ($amount < (float) $this->min_amount) {
            return false;
        }

        if ($this->max_amount !== null && $amount > (float) $this->max_amount) {
            return false;
        }

        return $this->step_key === null || $stepKey === null || $this->step_key === $stepKey;
    }
}
