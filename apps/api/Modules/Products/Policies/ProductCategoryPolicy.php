<?php

namespace Modules\Products\Policies;

class ProductCategoryPolicy extends LookupPolicy
{
    protected function permissionModule(): string
    {
        return 'categories';
    }
}
