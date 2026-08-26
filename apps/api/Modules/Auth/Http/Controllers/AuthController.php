<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Requests\RefreshRequest;
use Modules\Auth\Services\AuthService;
use Modules\Auth\Services\InvalidRefreshTokenException;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user === null || ! Hash::check($request->string('password'), $user->password)) {
            return ApiResponse::error(401, 'Invalid credentials.', 'InvalidCredentials');
        }

        $tokens = $this->auth->issuePair($user);

        return ApiResponse::success([
            'user' => new UserResource($user),
            ...$tokens,
            'token_type' => 'Bearer',
        ]);
    }

    public function refresh(RefreshRequest $request): JsonResponse
    {
        try {
            $tokens = $this->auth->rotate($request->string('refresh_token')->toString());
        } catch (InvalidRefreshTokenException $e) {
            return ApiResponse::error(401, $e->getMessage(), 'InvalidRefreshToken');
        }

        return ApiResponse::success([...$tokens, 'token_type' => 'Bearer']);
    }

    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        $this->auth->revokeFamily($user);

        return ApiResponse::success(['loggedOut' => true]);
    }

    public function me(): JsonResponse
    {
        return ApiResponse::success(new UserResource(request()->user()));
    }
}
