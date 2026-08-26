<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uom_id', 'name', 'short_name', 'conversion_factor', 'is_active'])]
class UomSubUnit extends LookupModel
{
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
