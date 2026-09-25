<?php

namespace Modules\Approvals\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Approvals\Database\Factories\ApprovalRequestFactory;

#[Fillable([
    'entity_id',
    'subject_type',
    'subject_id',
    'approval_setting_id',
    'approval_flow_id',
    'amount_snapshot',
    'status',
    'current_position',
    'current_assignee_id',
    'snapshot',
    'created_by',
    'updated_by',
    'decided_at',
])]
class ApprovalRequest extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): ApprovalRequestFactory
    {
        return ApprovalRequestFactory::new();
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RETURNED = 'returned';

    protected function casts(): array
    {
        return [
            'amount_snapshot' => 'decimal:2',
            'snapshot' => 'array',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<ApprovalFlow, $this>
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    /**
     * @return HasMany<ApprovalAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)->orderBy('step_position')->orderBy('acted_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
