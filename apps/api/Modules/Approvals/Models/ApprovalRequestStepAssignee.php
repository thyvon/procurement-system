<?php

namespace Modules\Approvals\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per responsible user per decide step of a round: the pending-task
 * surface for co-signed steps (unacted rows = work owed). `acted_at` null
 * until the user acts; a reject/return voids the remaining rows.
 */
#[Fillable([
    'entity_id',
    'approval_request_id',
    'step_position',
    'user_id',
    'acted_at',
])]
class ApprovalRequestStepAssignee extends Model
{
    use BelongsToEntity, HasUlids;

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ApprovalRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
