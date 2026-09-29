<?php

namespace Modules\Telegram\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Telegram\Database\Factories\TelegramContactFactory;

#[Fillable([
    'entity_id',
    'name',
    'phone',
    'username',
    'telegram_user_id',
    'telegram_chat_id',
    'telegram_account_id',
    'language',
    'supplier_code',
    'verified_at',
    'created_by',
    'updated_by',
])]
class TelegramContact extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    public const LANGUAGE_KM = 'km';

    public const LANGUAGE_EN = 'en';

    /**
     * Telegram usernames are case-insensitive — store them normalized.
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? strtolower($value) : null,
        );
    }

    protected function casts(): array
    {
        return [
            'telegram_user_id' => 'integer',
            'telegram_chat_id' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    protected static function newFactory(): TelegramContactFactory
    {
        return TelegramContactFactory::new();
    }

    /**
     * @return BelongsTo<TelegramAccount, $this>
     */
    public function telegramAccount(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'telegram_account_id');
    }

    /**
     * @return HasMany<TelegramMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class, 'telegram_contact_id');
    }
}
