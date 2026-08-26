<?php

namespace Modules\Products\Policies;

class ProductPolicy extends LookupPolicy
{
    // Same shape as lookups: everyone reads, admins manage.
    // Kept as its own class so per-product rules (e.g. requisition
    // integration) have an obvious home later.
}
