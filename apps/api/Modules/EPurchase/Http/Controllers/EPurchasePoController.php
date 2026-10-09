<?php

namespace Modules\EPurchase\Http\Controllers;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\EPurchase\Http\Requests\IndexEPurchasePosRequest;
use Modules\EPurchase\Http\Resources\EPurchasePoLineResource;
use Modules\EPurchase\Http\Resources\EPurchasePoResource;
use Modules\EPurchase\Services\EPurchaseSession;
use Modules\EPurchase\Services\EPurchaseSessionExpiredException;
use Modules\EPurchase\Services\EPurchaseUnavailableException;

class EPurchasePoController extends EPurchaseProxyController
{
    /**
     * Read-only proxy of the upstream purchase order list (server-side paging/search).
     */
    public function index(IndexEPurchasePosRequest $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 10);
        $page = (int) $request->integer('page', 1);
        $search = $request->string('search')->toString();

        try {
            /** @var array{recordsTotal: int, recordsFiltered: int, data: array<int, array<string, mixed>>} $result */
            $result = $this->withSessionRefresh(
                $request,
                fn (EPurchaseSession $session): array => $this->client->pos($session, ($page - 1) * $perPage, $perPage, $search)
            );
        } catch (EPurchaseSessionExpiredException|EPurchaseUnavailableException $exception) {
            return $this->clientFailureResponse($exception, $request);
        }

        return ApiResponse::success(
            EPurchasePoResource::collection(collect($result['data']))->resolve($request),
            200,
            [
                'page' => $page,
                'perPage' => $perPage,
                'total' => (int) $result['recordsFiltered'],
            ]
        );
    }

    /**
     * Read-only proxy of one upstream purchase order's line items.
     * The route constrains {poId} to numbers (a non-numeric id is a 404,
     * mirroring how model binding rejects unknown record ids).
     */
    public function show(Request $request, string $poId): JsonResponse
    {
        try {
            /** @var array<int, array<string, mixed>> $lines */
            $lines = $this->withSessionRefresh(
                $request,
                fn (EPurchaseSession $session): array => $this->client->poDetail($session, (int) $poId)
            );
        } catch (EPurchaseSessionExpiredException|EPurchaseUnavailableException $exception) {
            return $this->clientFailureResponse($exception, $request);
        }

        return ApiResponse::success(
            EPurchasePoLineResource::collection(collect($lines))->resolve($request)
        );
    }
}
