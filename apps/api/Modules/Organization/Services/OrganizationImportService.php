<?php

namespace Modules\Organization\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Organization\Models\Campus;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Division;
use Modules\Products\Services\CodeGenerationService;

/**
 * Spreadsheet import for organization lookups (campuses, departments).
 * Mirrors the Products ProductImportService recipe: validate each row, skip
 * bad lines and report them, insert everything in one transaction.
 */
class OrganizationImportService
{
    public function __construct(private readonly CodeGenerationService $codes) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int, errors: array<int, array{row: int, message: string}>}
     */
    public function importCampuses(User $actor, array $rows): array
    {
        $errors = [];
        $imported = 0;
        $seenNames = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $line = $index + 2; // header is line 1

                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    $errors[] = ['row' => $line, 'message' => 'Name is required.'];

                    continue;
                }

                $key = Str::lower($name);
                if (isset($seenNames[$key])) {
                    $errors[] = ['row' => $line, 'message' => "Duplicate name \"{$name}\" in this file."];

                    continue;
                }

                if (Campus::query()->where('entity_id', $actor->entity_id)->where('name', $name)->exists()) {
                    $errors[] = ['row' => $line, 'message' => "Name \"{$name}\" already exists."];

                    continue;
                }

                Campus::create([
                    'entity_id' => $actor->entity_id,
                    'name' => $name,
                    'short_code' => $this->codes->nextShortCode($name, Campus::class, $actor->entity_id),
                    'is_active' => $this->resolveStatus($row),
                    'created_by' => $actor->getKey(),
                    'updated_by' => $actor->getKey(),
                ]);

                $seenNames[$key] = true;
                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'imported' => $imported,
            'skipped' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int, errors: array<int, array{row: int, message: string}>}
     */
    public function importDepartments(User $actor, array $rows): array
    {
        $errors = [];
        $imported = 0;
        $seenNames = [];

        // Lookup cache so repeated division names resolve once.
        $divisions = Division::query()
            ->where('entity_id', $actor->entity_id)
            ->get(['id', 'name'])
            ->keyBy(fn (Division $division) => Str::lower($division->name));

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $line = $index + 2; // header is line 1

                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    $errors[] = ['row' => $line, 'message' => 'Name is required.'];

                    continue;
                }

                $key = Str::lower($name);
                if (isset($seenNames[$key])) {
                    $errors[] = ['row' => $line, 'message' => "Duplicate name \"{$name}\" in this file."];

                    continue;
                }

                if (Department::query()->where('entity_id', $actor->entity_id)->where('name', $name)->exists()) {
                    $errors[] = ['row' => $line, 'message' => "Name \"{$name}\" already exists."];

                    continue;
                }

                $divisionNames = array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) ($row['divisions'] ?? '')),
                )));

                if ($divisionNames === []) {
                    $errors[] = ['row' => $line, 'message' => 'At least one division is required.'];

                    continue;
                }

                $divisionIds = [];
                $unknown = null;
                foreach ($divisionNames as $divisionName) {
                    $division = $divisions->get(Str::lower($divisionName));
                    if ($division === null) {
                        $unknown = $divisionName;

                        break;
                    }
                    $divisionIds[] = $division->getKey();
                }

                if ($unknown !== null) {
                    $errors[] = ['row' => $line, 'message' => "Division \"{$unknown}\" not found."];

                    continue;
                }

                $department = Department::create([
                    'entity_id' => $actor->entity_id,
                    'name' => $name,
                    'short_code' => $this->codes->nextShortCode($name, Department::class, $actor->entity_id),
                    'is_active' => $this->resolveStatus($row),
                    'created_by' => $actor->getKey(),
                    'updated_by' => $actor->getKey(),
                ]);

                $department->divisions()->sync($divisionIds);

                $seenNames[$key] = true;
                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'imported' => $imported,
            'skipped' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function campusTemplateRows(): array
    {
        return [
            'name,status',
            'Main Campus,active',
            'North Campus,active',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function departmentTemplateRows(): array
    {
        return [
            'name,divisions,status',
            'Accounting,"Finance,HR",active',
            'Procurement,Operations,active',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveStatus(array $row): bool
    {
        return Str::lower(trim((string) ($row['status'] ?? 'active'))) !== 'inactive';
    }
}
