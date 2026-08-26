<?php

namespace Modules\Products\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base for simple product-module lookup entities (brands, groups, uoms...).
 * Carries the traits and casts every lookup table shares.
 */
abstract class LookupModel extends Model
{
    use BelongsToEntity, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
