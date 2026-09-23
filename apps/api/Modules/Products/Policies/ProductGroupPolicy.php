<?php

namespace Modules\Products\Policies;

class ProductGroupPolicy extends LookupPolicy
{
    protected function permissionModule(): string
    {
        return 'groups';
    }
}
