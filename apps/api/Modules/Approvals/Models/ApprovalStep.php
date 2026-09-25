<?php

namespace Modules\Approvals\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'entity_id',
    'approval_flow_id',
    'position',
    'key',
    'label',
    'action_mode',
    'allowed_actions',
])]
class ApprovalStep extends Model
{
    use BelongsToEntity, HasUlids, SoftDeletes;

    public const MODE_RECORD = 'record';

    public const MODE_DECIDE = 'decide';

    protected function casts(): array
    {
        return [
            'allowed_actions' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ApprovalFlow, $this>
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function isDecide(): bool
    {
        return $this->action_mode === self::MODE_DECIDE;
    }
}
