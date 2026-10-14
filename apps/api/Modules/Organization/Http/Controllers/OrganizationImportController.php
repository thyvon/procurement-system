<?php

namespace Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Organization\Http\Requests\StoreCampusImportRequest;
use Modules\Organization\Http\Requests\StoreDepartmentImportRequest;
use Modules\Organization\Models\Campus;
use Modules\Organization\Models\Department;
use Modules\Organization\Services\OrganizationImportService;
use Symfony\Component\HttpFoundation\Response;

class OrganizationImportController extends Controller
{
    public function __construct(private readonly OrganizationImportService $importer) {}

    public function campusTemplate(): Response
    {
        $this->authorize('create', Campus::class);

        return $this->csvResponse(
            $this->importer->campusTemplateRows(),
            'campuses-import-template.csv',
        );
    }

    public function storeCampuses(StoreCampusImportRequest $request): JsonResponse
    {
        $this->authorize('create', Campus::class);

        $result = $this->importer->importCampuses(
            $request->user(),
            $request->validated('rows'),
        );

        return ApiResponse::success($result);
    }

    public function departmentTemplate(): Response
    {
        $this->authorize('create', Department::class);

        return $this->csvResponse(
            $this->importer->departmentTemplateRows(),
            'departments-import-template.csv',
        );
    }

    public function storeDepartments(StoreDepartmentImportRequest $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        $result = $this->importer->importDepartments(
            $request->user(),
            $request->validated('rows'),
        );

        return ApiResponse::success($result);
    }

    /**
     * @param  array<int, string>  $rows
     */
    private function csvResponse(array $rows, string $filename): Response
    {
        return response(implode("\n", $rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
