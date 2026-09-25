<?php

namespace Modules\Approvals\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'entity_id',
    'subject_type',
    'name',
    'is_active',
    'created_by',
    'updated_by',
])]
class ApprovalSetting extends Model
{
    use BelongsToEntity, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ApprovalFlow, $this>
     */
    public function flows(): HasMany
    {
        return $this->hasMany(ApprovalFlow::class);
    }
}
