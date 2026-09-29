<?php

namespace Modules\Telegram\Models;

use App\Support\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Telegram\Database\Factories\TelegramMessageFactory;

#[Fillable([
    'entity_id',
    'direction',
    'telegram_account_id',
    'telegram_contact_id',
    'telegram_chat_id',
    'telegram_message_id',
    'reference_type',
    'reference_id',
    'body',
    'file_path',
    'status',
    'idempotency_key',
    'parsed_json',
    'error',
    'created_by',
    'updated_by',
])]
class TelegramMessage extends Model
{
    use BelongsToEntity, HasFactory, HasUlids, SoftDeletes;

    public const DIRECTION_OUT = 'out';

    public const DIRECTION_IN = 'in';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_SENT,
        self::STATUS_FAILED,
        self::STATUS_RECEIVED,
        self::STATUS_NEEDS_REVIEW,
    ];

    protected function casts(): array
    {
        return [
            'telegram_chat_id' => 'integer',
            'telegram_message_id' => 'integer',
            'parsed_json' => 'array',
        ];
    }

    protected static function newFactory(): TelegramMessageFactory
    {
        return TelegramMessageFactory::new();
    }

    /**
     * @return BelongsTo<TelegramAccount, $this>
     */
    public function telegramAccount(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'telegram_account_id');
    }

    /**
     * @return BelongsTo<TelegramContact, $this>
     */
    public function telegramContact(): BelongsTo
    {
        return $this->belongsTo(TelegramContact::class, 'telegram_contact_id');
    }
}
