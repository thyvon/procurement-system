<?php

namespace Modules\Telegram\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Telegram\Models\TelegramContact;

class TelegramContactRepository extends BaseRepository implements TelegramContactRepositoryInterface
{
    public function __construct(TelegramContact $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()->orderBy('name')->cursorPaginate($perPage);
    }
}
