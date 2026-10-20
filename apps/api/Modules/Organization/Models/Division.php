<?php

namespace Modules\Organization\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Organization\Database\Factories\DivisionFactory;

#[Fillable([
    'entity_id',
    'name',
    'short_code',
    'is_active',
    'created_by',
    'updated_by',
])]
class Division extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): DivisionFactory
    {
        return DivisionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The departments that belong to this division (a department may serve
     * several divisions).
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_division');
    }
}
