<?php

namespace Modules\Organization\Policies;

/**
 * System settings read with `settings.view`, write with `settings.manage`
 * (see PermissionSeeder). Class-string abilities work because the model
 * parameter is nullable, matching the OrgLookupPolicy shape.
 */
class SystemSettingPolicy extends OrgLookupPolicy
{
    protected function permissionModule(): string
    {
        return 'settings';
    }
}
