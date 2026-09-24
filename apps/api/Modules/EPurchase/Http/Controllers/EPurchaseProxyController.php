<?php

namespace Modules\EPurchase\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Modules\EPurchase\Services\EPurchaseClient;
use Modules\EPurchase\Services\EPurchaseSession;
use Modules\EPurchase\Services\EPurchaseSessionExpiredException;
use Modules\EPurchase\Services\EPurchaseSessionService;
use Modules\EPurchase\Services\EPurchaseUnavailableException;
use Throwable;

/**
 * Shared session + error envelope for read-only E-Purchase proxies.
 * No policy/permission for v1 — auth:sanctum only (mirrors ProductRefController).
 */
abstract class EPurchaseProxyController extends Controller
{
    public function __construct(
        protected readonly EPurchaseClient $client,
        protected readonly EPurchaseSessionService $sessions,
    ) {}

    protected function session(FormRequest $request): ?EPurchaseSession
    {
        $user = $request->user();

        return $user !== null ? $this->sessions->get($user->getAuthIdentifier()) : null;
    }

    protected function forgetSession(FormRequest $request): void
    {
        $user = $request->user();

        if ($user !== null) {
            $this->sessions->forget($user->getAuthIdentifier());
        }
    }

    protected function sessionExpiredResponse(): JsonResponse
    {
        return ApiResponse::error(401, 'Company session expired. Please log in again.', 'EPurchaseSessionExpired');
    }

    protected function unavailableResponse(Throwable $exception): JsonResponse
    {
        report($exception);

        return ApiResponse::error(502, 'Company list is temporarily unavailable.', 'EPurchaseUnavailable');
    }

    /**
     * Map client domain failures to the proxy error envelope.
     * SessionExpired clears the cached company session first.
     *
     * @throws Throwable when the exception is not a known proxy failure
     */
    protected function clientFailureResponse(Throwable $exception, FormRequest $request): JsonResponse
    {
        if ($exception instanceof EPurchaseSessionExpiredException) {
            $this->forgetSession($request);

            return $this->sessionExpiredResponse();
        }

        if ($exception instanceof EPurchaseUnavailableException) {
            return $this->unavailableResponse($exception);
        }

        throw $exception;
    }
}
