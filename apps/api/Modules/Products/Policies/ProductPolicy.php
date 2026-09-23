<?php

namespace Modules\Products\Policies;

class ProductPolicy extends LookupPolicy
{
    // Kept as its own class so per-product rules (e.g. requisition
    // integration) have an obvious home later.

    protected function permissionModule(): string
    {
        return 'products';
    }
}
