<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active', 'created_by', 'updated_by'])]
class VariationTemplate extends LookupModel
{
    /**
     * @return HasMany<VariationTemplateOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(VariationTemplateOption::class)->orderBy('sort_order');
    }
}
