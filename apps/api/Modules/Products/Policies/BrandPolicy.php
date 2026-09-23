<?php

namespace Modules\Products\Policies;

class BrandPolicy extends LookupPolicy
{
    protected function permissionModule(): string
    {
        return 'brands';
    }
}
