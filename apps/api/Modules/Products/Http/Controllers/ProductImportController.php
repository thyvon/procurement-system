<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Products\Models\Product;
use Modules\Products\Services\ProductImportService;
use Symfony\Component\HttpFoundation\Response;

class ProductImportController extends Controller
{
    public function __construct(private readonly ProductImportService $importer) {}

    public function template(): Response
    {
        $this->authorize('create', Product::class);

        $csv = implode("\n", $this->importer->templateRows());

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products-import-template.csv"',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $validator = Validator::make($request->all(), [
            'rows' => ['required', 'array', 'max:5000'],
            // Structural checks only — missing code/name are reported
            // per-row by the import service, not fatal for the file.
            'rows.*.code' => ['nullable', 'string'],
            'rows.*.name' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(422, $validator->errors()->first(), 'ValidationException');
        }

        /** @var User $user */
        $user = $request->user();

        $result = $this->importer->import($user, $request->input('rows'));

        return ApiResponse::success($result);
    }
}
