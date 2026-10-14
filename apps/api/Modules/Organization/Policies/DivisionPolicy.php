<?php

namespace Modules\Organization\Policies;

class DivisionPolicy extends OrgLookupPolicy
{
    protected function permissionModule(): string
    {
        return 'divisions';
    }
}
