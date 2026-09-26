<?php

namespace Modules\Approvals\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'entity_id',
    'subject_type',
    'subject_id',
    'assignees',
    'created_by',
    'updated_by',
])]
class ApprovalDraft extends Model
{
    use BelongsToEntity, HasUlids;

    protected function casts(): array
    {
        return [
            'assignees' => 'array',
        ];
    }
}
