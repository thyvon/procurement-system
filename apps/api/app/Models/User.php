<?php

namespace App\Models;

use App\Support\Concerns\BelongsToEntity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\EmployeeProfiles\Models\EmployeeProfile;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string|null $entity_id
 * @property string|null $avatar_path
 * @property string|null $signature_path
 * @property int|null $telegram_chat_id
 */
#[Fillable(['name', 'email', 'password', 'avatar_path', 'entity_id', 'is_active', 'signature_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /**
     * Single-guard API: all roles/permissions resolve on the sanctum guard.
     */
    protected string $guard_name = 'sanctum';

    /** @use HasFactory<UserFactory> */
    use BelongsToEntity, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'telegram_chat_id' => 'integer',
        ];
    }

    /**
     * @return HasOne<EmployeeProfile, $this>
     */
    public function currentEmployeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class)
            ->where('is_current', true)
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at');
    }

    /**
     * @return HasMany<EmployeeProfile, $this>
     */
    public function employeeProfiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class)->orderByDesc('effective_from');
    }
}
