<?php

namespace Modules\EPurchase\Http\Controllers;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\EPurchase\Http\Requests\IndexEPurchaseSuppliersRequest;
use Modules\EPurchase\Http\Resources\EPurchaseSupplierResource;
use Modules\EPurchase\Services\EPurchaseSessionExpiredException;
use Modules\EPurchase\Services\EPurchaseUnavailableException;

class EPurchaseSupplierController extends EPurchaseProxyController
{
    /**
     * Read-only proxy of the upstream suppliers list (server-side paging/search).
     */
    public function index(IndexEPurchaseSuppliersRequest $request): JsonResponse
    {
        $session = $this->session($request);

        if ($session === null) {
            return $this->sessionExpiredResponse();
        }

        $perPage = (int) $request->integer('per_page', 10);
        $page = (int) $request->integer('page', 1);
        $search = $request->string('search')->toString();

        try {
            $result = $this->client->suppliers($session, ($page - 1) * $perPage, $perPage, $search);
        } catch (EPurchaseSessionExpiredException|EPurchaseUnavailableException $exception) {
            return $this->clientFailureResponse($exception, $request);
        }

        $rows = $result['data'];
        $total = $result['recordsFiltered'];

        // Upstream cannot filter by onboarding — narrow the fetched page here.
        // Best-effort: totals reflect this page only while the filter is active.
        if ($request->has('is_onboard')) {
            $wantOnboarded = $request->string('is_onboard')->toString() === '1';
            $rows = array_values(array_filter($rows, function (array $row) use ($wantOnboarded): bool {
                $flag = strtolower((string) ($row['is_onboard'] ?? ''));
                $onboarded = $flag === '1' || $flag === 'onboarded';

                return $onboarded === $wantOnboarded;
            }));
            $total = count($rows);
        }

        return ApiResponse::success(
            EPurchaseSupplierResource::collection(collect($rows))->resolve($request),
            200,
            [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
            ]
        );
    }
}
