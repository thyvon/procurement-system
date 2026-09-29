<?php

namespace Modules\Telegram\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Telegram\Database\Factories\TelegramAccountFactory;

#[Fillable([
    'entity_id',
    'label',
    'phone',
    'state',
    'tdlib_base_url',
    'last_seen_at',
    'flood_wait_until',
    'created_by',
    'updated_by',
])]
class TelegramAccount extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    public const STATE_WAITING_CODE = 'waiting_code';

    public const STATE_READY = 'ready';

    public const STATE_DISCONNECTED = 'disconnected';

    public const STATE_THROTTLED = 'throttled';

    public const STATE_BANNED = 'banned';

    /**
     * @var array<int, string>
     */
    public const STATES = [
        self::STATE_WAITING_CODE,
        self::STATE_READY,
        self::STATE_DISCONNECTED,
        self::STATE_THROTTLED,
        self::STATE_BANNED,
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'flood_wait_until' => 'datetime',
        ];
    }

    protected static function newFactory(): TelegramAccountFactory
    {
        return TelegramAccountFactory::new();
    }

    /**
     * @return HasMany<TelegramContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(TelegramContact::class, 'telegram_account_id');
    }

    /**
     * @return HasMany<TelegramMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class, 'telegram_account_id');
    }
}
