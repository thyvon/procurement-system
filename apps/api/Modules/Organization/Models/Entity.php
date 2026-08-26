<?php

namespace Modules\Organization\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'name',
    'timezone',
    'locale',
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
