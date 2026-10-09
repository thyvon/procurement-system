<?php

namespace Modules\EPurchase\Http\Controllers;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\EPurchase\Http\Requests\IndexEPurchasePrsRequest;
use Modules\EPurchase\Http\Resources\EPurchasePrLineResource;
use Modules\EPurchase\Http\Resources\EPurchasePrResource;
use Modules\EPurchase\Services\EPurchaseSession;
use Modules\EPurchase\Services\EPurchaseSessionExpiredException;
use Modules\EPurchase\Services\EPurchaseUnavailableException;

class EPurchasePrController extends EPurchaseProxyController
{
    /**
     * Read-only proxy of the upstream purchase requisition list (server-side paging/search).
     */
    public function index(IndexEPurchasePrsRequest $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 10);
        $page = (int) $request->integer('page', 1);
        $search = $request->string('search')->toString();

        try {
            /** @var array{recordsTotal: int, recordsFiltered: int, data: array<int, array<string, mixed>>} $result */
            $result = $this->withSessionRefresh(
                $request,
                fn (EPurchaseSession $session): array => $this->client->prs($session, ($page - 1) * $perPage, $perPage, $search)
            );
        } catch (EPurchaseSessionExpiredException|EPurchaseUnavailableException $exception) {
            return $this->clientFailureResponse($exception, $request);
        }

        return ApiResponse::success(
            EPurchasePrResource::collection(collect($result['data']))->resolve($request),
            200,
            [
                'page' => $page,
                'perPage' => $perPage,
                'total' => (int) $result['recordsFiltered'],
            ]
        );
    }

    /**
     * Read-only proxy of one upstream purchase requisition's line items.
     * The route constrains {prId} to numbers (a non-numeric id is a 404,
     * mirroring how model binding rejects unknown record ids).
     */
    public function show(Request $request, string $prId): JsonResponse
    {
        try {
            /** @var array<int, array<string, mixed>> $lines */
            $lines = $this->withSessionRefresh(
                $request,
                fn (EPurchaseSession $session): array => $this->client->prDetail($session, (int) $prId)
            );
        } catch (EPurchaseSessionExpiredException|EPurchaseUnavailableException $exception) {
            return $this->clientFailureResponse($exception, $request);
        }

        return ApiResponse::success(
            EPurchasePrLineResource::collection(collect($lines))->resolve($request)
        );
    }
}
