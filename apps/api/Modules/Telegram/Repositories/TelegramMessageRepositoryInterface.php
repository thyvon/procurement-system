<?php

namespace Modules\Telegram\Repositories;

use App\Support\Repository\RepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface TelegramMessageRepositoryInterface extends RepositoryInterface
{
    /**
     * One contact's conversation, newest first.
     */
    public function forContact(string $telegramContactId, int $perPage = 50): CursorPaginator;
}
