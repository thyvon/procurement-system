<?php

namespace Modules\EPurchase\Http\Controllers;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\EPurchase\Http\Requests\SearchEPurchaseVendorsRequest;
use Modules\EPurchase\Http\Requests\ShowEPurchaseVendorInfoRequest;
use Modules\EPurchase\Http\Resources\EPurchaseVendorInfoResource;
use Modules\EPurchase\Http\Resources\EPurchaseVendorSearchResource;
use Modules\EPurchase\Services\EPurchaseSessionExpiredException;
use Modules\EPurchase\Services\EPurchaseUnavailableException;

class EPurchaseVendorController extends EPurchaseProxyController
{
    /**
     * Read-only proxy of upstream /api/po/getVentors (autocomplete search).
     */
    public function search(SearchEPurchaseVendorsRequest $request): JsonResponse
    {
        $session = $this->session($request);

        if ($session === null) {
            return $this->sessionExpiredResponse();
        }

        $term = $request->string('term')->toString();

        try {
            $rows = $this->client->vendorSearch($session, $term);
        } catch (EPurchaseSessionExpiredException|EPurchaseUnavailableException $exception) {
            return $this->clientFailureResponse($exception, $request);
        }

        return ApiResponse::success(EPurchaseVendorSearchResource::collection(collect($rows))->resolve($request));
    }

    /**
     * Read-only proxy of upstream /api/po/getVendorInfo (full vendor profile).
     */
    public function info(ShowEPurchaseVendorInfoRequest $request): JsonResponse
    {
        $session = $this->session($request);

        if ($session === null) {
            return $this->sessionExpiredResponse();
        }

        $supplierCode = $request->string('supplier_code')->toString();

        try {
            $data = $this->client->vendorInfo($session, $supplierCode);
        } catch (EPurchaseSessionExpiredException|EPurchaseUnavailableException $exception) {
            return $this->clientFailureResponse($exception, $request);
        }

        return ApiResponse::success((new EPurchaseVendorInfoResource($data))->resolve($request));
    }
}
