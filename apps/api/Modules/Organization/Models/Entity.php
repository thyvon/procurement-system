<?php

namespace Modules\Organization\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string|null $logo_path
 */
#[Fillable([
    'code',
    'name',
    'timezone',
    'locale',
    'logo_path',
    'is_active',
    'created_by',
    'updated_by',
])]
class Entity extends Model
{
    use HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
