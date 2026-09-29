<?php

namespace Modules\Telegram\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Telegram\Http\Requests\LoginCodeRequest;
use Modules\Telegram\Http\Requests\LoginPasswordRequest;
use Modules\Telegram\Http\Requests\StoreTelegramAccountRequest;
use Modules\Telegram\Http\Requests\UpdateTelegramAccountRequest;
use Modules\Telegram\Http\Resources\TelegramAccountResource;
use Modules\Telegram\Models\TelegramAccount;
use Modules\Telegram\Repositories\TelegramAccountRepositoryInterface;
use Modules\Telegram\Services\TelegramGateway;

class TelegramAccountController extends Controller
{
    public function __construct(
        private readonly TelegramAccountRepositoryInterface $repo,
        private readonly TelegramGateway $gateway,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TelegramAccount::class);

        return ApiResponse::success(
            TelegramAccountResource::collection($this->repo->paginate(100))->resolve($request),
        );
    }

    public function store(StoreTelegramAccountRequest $request): JsonResponse
    {
        $this->authorize('create', TelegramAccount::class);

        /** @var User $user */
        $user = $request->user();

        $account = $this->repo->create([
            ...$request->validated(),
            'state' => TelegramAccount::STATE_DISCONNECTED,
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        return ApiResponse::success(new TelegramAccountResource($account), 201);
    }

    public function show(Request $request, TelegramAccount $account): JsonResponse
    {
        $this->authorize('view', $account);

        return ApiResponse::success(new TelegramAccountResource($account));
    }

    public function update(UpdateTelegramAccountRequest $request, TelegramAccount $account): JsonResponse
    {
        $this->authorize('update', $account);

        /** @var User $user */
        $user = $request->user();

        $updated = $this->repo->update($account, [
            ...$request->validated(),
            'updated_by' => $user->getKey(),
        ]);

        return ApiResponse::success(new TelegramAccountResource($updated));
    }

    public function destroy(TelegramAccount $account): JsonResponse
    {
        $this->authorize('delete', $account);

        $this->repo->delete($account);

        return ApiResponse::success(['deleted' => true]);
    }

    public function loginCode(LoginCodeRequest $request, TelegramAccount $account): JsonResponse
    {
        $this->authorize('update', $account);

        return ApiResponse::success($this->gateway->loginCode($account, $request->validated('code')));
    }

    public function loginPassword(LoginPasswordRequest $request, TelegramAccount $account): JsonResponse
    {
        $this->authorize('update', $account);

        return ApiResponse::success($this->gateway->loginPassword($account, $request->validated('password')));
    }

    public function health(Request $request, TelegramAccount $account): JsonResponse
    {
        $this->authorize('view', $account);

        return ApiResponse::success($this->gateway->health($account));
    }
}
