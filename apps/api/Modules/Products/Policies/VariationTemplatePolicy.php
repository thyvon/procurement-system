<?php

namespace Modules\Products\Policies;

class VariationTemplatePolicy extends LookupPolicy
{
    protected function permissionModule(): string
    {
        return 'variations';
    }
}
