<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Http\Requests\CompanyLoginRequest;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Requests\RefreshRequest;
use Modules\Auth\Services\AuthService;
use Modules\Auth\Services\CompanyLoginService;
use Modules\Auth\Services\InvalidRefreshTokenException;
use Modules\EPurchase\Services\EPurchaseSessionService;
use Modules\EPurchase\Services\EPurchaseUnavailableException;
use Modules\EPurchase\Services\InvalidCompanyCredentialsException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly CompanyLoginService $companyLogin,
        private readonly EPurchaseSessionService $epurchaseSessions,
    ) {}

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

    public function companyLogin(CompanyLoginRequest $request): JsonResponse
    {
        try {
            $data = $this->companyLogin->login(
                $request->string('employee_id')->toString(),
                $request->string('password')->toString(),
            );
        } catch (InvalidCompanyCredentialsException) {
            return ApiResponse::error(401, 'Invalid credentials.', 'InvalidCredentials');
        } catch (EPurchaseUnavailableException $exception) {
            report($exception);

            return ApiResponse::error(502, 'Company login is temporarily unavailable.', 'EPurchaseUnavailable');
        }

        return ApiResponse::success([
            'user' => new UserResource($data['user']),
            ...$data['tokens'],
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
        $this->epurchaseSessions->forget($user->getAuthIdentifier());

        return ApiResponse::success(['loggedOut' => true]);
    }

    public function me(): JsonResponse
    {
        return ApiResponse::success(new UserResource(request()->user()->load('roles')));
    }
}
