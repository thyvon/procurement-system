<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['code', 'name', 'description', 'is_active', 'created_by', 'updated_by'])]
class Brand extends LookupModel {}
