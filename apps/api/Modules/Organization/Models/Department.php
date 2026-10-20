<?php

namespace Modules\Organization\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Organization\Database\Factories\DepartmentFactory;

#[Fillable([
    'entity_id',
    'name',
    'short_code',
    'is_active',
    'created_by',
    'updated_by',
])]
class Department extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): DepartmentFactory
    {
        return DepartmentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The divisions this department serves (many-to-many).
     *
     * @return BelongsToMany<Division, $this>
     */
    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class, 'department_division');
    }
}
