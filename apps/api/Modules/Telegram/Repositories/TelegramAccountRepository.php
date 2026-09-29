<?php

namespace Modules\Telegram\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Telegram\Models\TelegramAccount;

class TelegramAccountRepository extends BaseRepository implements TelegramAccountRepositoryInterface
{
    public function __construct(TelegramAccount $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()->orderBy('label')->cursorPaginate($perPage);
    }
}
