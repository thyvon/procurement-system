<?php

namespace Modules\Organization\Services;

use Modules\Organization\Models\Department;
use Modules\Organization\Models\Division;

/**
 * Multi-model writes for organization lookups — the division ⇄ department
 * many-to-many is the only write that spans tables. Either side may drive
 * the sync; both hit the same pivot.
 */
class OrganizationLookupService
{
    /**
     * Replace a division's departments list (full-replace, same semantics
     * as the invoice line list).
     *
     * @param  array<int, string>  $departmentIds
     */
    public function syncDepartmentIds(Division $division, array $departmentIds): void
    {
        $division->departments()->sync($departmentIds);
    }

    /**
     * Replace a department's divisions list (mirror of syncDepartmentIds).
     *
     * @param  array<int, string>  $divisionIds
     */
    public function syncDivisionIds(Department $department, array $divisionIds): void
    {
        $department->divisions()->sync($divisionIds);
    }
}
