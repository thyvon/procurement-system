<?php

namespace Modules\Telegram\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Telegram\Models\TelegramMessage;

class TelegramMessageRepository extends BaseRepository implements TelegramMessageRepositoryInterface
{
    public function __construct(TelegramMessage $model)
    {
        parent::__construct($model);
    }

    public function forContact(string $telegramContactId, int $perPage = 50): CursorPaginator
    {
        return $this->query()
            ->where('telegram_contact_id', $telegramContactId)
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);
    }
}
