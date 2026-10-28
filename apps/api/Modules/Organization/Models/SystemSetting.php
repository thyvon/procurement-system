<?php

namespace Modules\Organization\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Organization\Database\Factories\SystemSettingFactory;

/**
 * A single system-setting override row (group + key → value).
 *
 * Values are encrypted at rest via the `encrypted` cast — plaintext never
 * reaches the database. Rows are never deleted: clearing an override writes
 * null (the environment value applies again), which also keeps the
 * (group, key) unique index intact. The pair must exist in
 * config/system-settings.php — unknown pairs are ignored by the config
 * overlay, so database content cannot point at arbitrary config paths.
 */
#[Fillable([
    'group',
    'key',
    'value',
    'created_by',
    'updated_by',
])]
class SystemSetting extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): SystemSettingFactory
    {
        return SystemSettingFactory::new();
    }

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }
}
