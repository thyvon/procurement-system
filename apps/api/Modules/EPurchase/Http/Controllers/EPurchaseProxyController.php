<?php

namespace Modules\EPurchase\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    protected function session(Request $request): ?EPurchaseSession
    {
        $user = $request->user();

        return $user !== null ? $this->sessions->get($user->getAuthIdentifier()) : null;
    }

    /**
     * Run an upstream read against the cached company session, renewing it
     * through the refresh endpoint once when the company system rejects it.
     *
     * All failures propagate so clientFailureResponse() stays the single
     * mapper: a rejected refresh or a rejected retry lands there and clears
     * the cached session.
     *
     * @param  callable(EPurchaseSession): mixed  $operation
     *
     * @throws EPurchaseSessionExpiredException when the session cannot be renewed
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    protected function withSessionRefresh(Request $request, callable $operation): mixed
    {
        $session = $this->session($request);

        if ($session === null) {
            throw new EPurchaseSessionExpiredException('Company session expired. Please log in again.');
        }

        try {
            return $operation($session);
        } catch (EPurchaseSessionExpiredException) {
            // The cached JWT was rejected upstream — renew it, then retry once.
        }

        $refreshed = $this->client->refresh($session);

        $user = $request->user();

        if ($user !== null) {
            $this->sessions->put($user->getAuthIdentifier(), $refreshed);
        }

        return $operation($refreshed);
    }

    protected function forgetSession(Request $request): void
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
    protected function clientFailureResponse(Throwable $exception, Request $request): JsonResponse
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
