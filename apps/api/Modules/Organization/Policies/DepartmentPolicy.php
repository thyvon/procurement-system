<?php

namespace Modules\Organization\Policies;

class DepartmentPolicy extends OrgLookupPolicy
{
    protected function permissionModule(): string
    {
        return 'departments';
    }
}
