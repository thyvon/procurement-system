<?php

namespace Modules\Telegram\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Telegram\Database\Factories\TelegramLinkCodeFactory;

/**
 * One single-use deep-link code (t.me/<bot>?start=<code>) issued to a user
 * to bind their direct chat to users.telegram_chat_id. The row records who
 * issued it (user_id/created_by), when it dies (expires_at) and which chat
 * redeemed it (used_at/telegram_chat_id). Redeemed exactly once — the
 * used_at claim is an atomic whereNull update in TelegramLinkService.
 */
#[Fillable([
    'entity_id',
    'user_id',
    'code',
    'expires_at',
    'used_at',
    'telegram_chat_id',
    'created_by',
    'updated_by',
])]
class TelegramLinkCode extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'telegram_chat_id' => 'integer',
        ];
    }

    protected static function newFactory(): TelegramLinkCodeFactory
    {
        return TelegramLinkCodeFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
