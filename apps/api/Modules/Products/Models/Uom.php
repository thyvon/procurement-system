<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'short_name', 'is_active', 'created_by', 'updated_by'])]
class Uom extends LookupModel
{
    /**
     * @return HasMany<UomSubUnit, $this>
     */
    public function subUnits(): HasMany
    {
        return $this->hasMany(UomSubUnit::class)->orderBy('conversion_factor');
    }
}
