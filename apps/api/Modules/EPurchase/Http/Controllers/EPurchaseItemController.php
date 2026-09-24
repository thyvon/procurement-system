<?php

namespace Modules\EPurchase\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\EPurchase\Http\Requests\IndexEPurchaseItemsRequest;
use Modules\EPurchase\Http\Resources\EPurchaseItemResource;
use Modules\EPurchase\Services\EPurchaseClient;
use Modules\EPurchase\Services\EPurchaseSessionExpiredException;
use Modules\EPurchase\Services\EPurchaseSessionService;
use Modules\EPurchase\Services\EPurchaseUnavailableException;

class EPurchaseItemController extends Controller
{
    public function __construct(
        private readonly EPurchaseClient $client,
        private readonly EPurchaseSessionService $sessions,
    ) {}

    /**
     * Read-only proxy of the upstream items list (server-side paging/search).
     * No policy/permission for v1 — auth:sanctum only (mirrors ProductRefController).
     */
    public function index(IndexEPurchaseItemsRequest $request): JsonResponse
    {
        $user = $request->user();
        $session = $user !== null ? $this->sessions->get($user->getAuthIdentifier()) : null;

        if ($session === null) {
            return ApiResponse::error(401, 'Company session expired. Please log in again.', 'EPurchaseSessionExpired');
        }

        $perPage = (int) $request->integer('per_page', 10);
        $page = (int) $request->integer('page', 1);
        $search = $request->string('search')->toString();

        try {
            $result = $this->client->items($session, ($page - 1) * $perPage, $perPage, $search);
        } catch (EPurchaseSessionExpiredException) {
            if ($user !== null) {
                $this->sessions->forget($user->getAuthIdentifier());
            }

            return ApiResponse::error(401, 'Company session expired. Please log in again.', 'EPurchaseSessionExpired');
        } catch (EPurchaseUnavailableException $exception) {
            report($exception);

            return ApiResponse::error(502, 'Company list is temporarily unavailable.', 'EPurchaseUnavailable');
        }

        return ApiResponse::success(
            EPurchaseItemResource::collection(collect($result['data']))->resolve($request),
            200,
            [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $result['recordsFiltered'],
            ]
        );
    }
}
