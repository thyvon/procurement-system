<?php

namespace Modules\Organization\Policies;

class CampusPolicy extends OrgLookupPolicy
{
    protected function permissionModule(): string
    {
        return 'campuses';
    }
}
