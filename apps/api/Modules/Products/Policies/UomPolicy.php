<?php

namespace Modules\Products\Policies;

class UomPolicy extends LookupPolicy
{
    protected function permissionModule(): string
    {
        return 'uoms';
    }
}
