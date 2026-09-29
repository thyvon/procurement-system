<?php

namespace Modules\Telegram\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Telegram\Http\Requests\StoreTelegramContactRequest;
use Modules\Telegram\Http\Requests\UpdateTelegramContactRequest;
use Modules\Telegram\Http\Resources\TelegramContactResource;
use Modules\Telegram\Models\TelegramAccount;
use Modules\Telegram\Models\TelegramContact;
use Modules\Telegram\Repositories\TelegramContactRepositoryInterface;

class TelegramContactController extends Controller
{
    public function __construct(
        private readonly TelegramContactRepositoryInterface $repo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TelegramContact::class);

        return ApiResponse::success(
            TelegramContactResource::collection($this->repo->paginate(100))->resolve($request),
        );
    }

    public function store(StoreTelegramContactRequest $request): JsonResponse
    {
        $this->authorize('create', TelegramContact::class);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();

        if (empty($data['telegram_account_id'])) {
            $data['telegram_account_id'] = TelegramAccount::query()
                ->orderBy('created_at')
                ->value('id');
        }

        $contact = $this->repo->create([
            ...$data,
            'language' => $data['language'] ?? TelegramContact::LANGUAGE_EN,
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        return ApiResponse::success(new TelegramContactResource($contact), 201);
    }

    public function show(Request $request, TelegramContact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        return ApiResponse::success(new TelegramContactResource($contact));
    }

    public function update(UpdateTelegramContactRequest $request, TelegramContact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        /** @var User $user */
        $user = $request->user();

        $updated = $this->repo->update($contact, [
            ...$request->validated(),
            'updated_by' => $user->getKey(),
        ]);

        return ApiResponse::success(new TelegramContactResource($updated));
    }

    public function destroy(TelegramContact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);

        $this->repo->delete($contact);

        return ApiResponse::success(['deleted' => true]);
    }
}
