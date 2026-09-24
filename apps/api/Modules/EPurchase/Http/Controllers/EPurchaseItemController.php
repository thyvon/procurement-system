<?php

namespace Modules\EPurchase\Http\Controllers;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\EPurchase\Http\Requests\IndexEPurchaseItemsRequest;
use Modules\EPurchase\Http\Resources\EPurchaseItemResource;
use Modules\EPurchase\Services\EPurchaseSessionExpiredException;
use Modules\EPurchase\Services\EPurchaseUnavailableException;

class EPurchaseItemController extends EPurchaseProxyController
{
    /**
     * Read-only proxy of the upstream items list (server-side paging/search).
     */
    public function index(IndexEPurchaseItemsRequest $request): JsonResponse
    {
        $session = $this->session($request);

        if ($session === null) {
            return $this->sessionExpiredResponse();
        }

        $perPage = (int) $request->integer('per_page', 10);
        $page = (int) $request->integer('page', 1);
        $search = $request->string('search')->toString();

        try {
            $result = $this->client->items($session, ($page - 1) * $perPage, $perPage, $search);
        } catch (EPurchaseSessionExpiredException|EPurchaseUnavailableException $exception) {
            return $this->clientFailureResponse($exception, $request);
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
