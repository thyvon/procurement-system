<?php

namespace Modules\Products\Repositories;

use App\Support\Repository\RepositoryInterface;
use Modules\Products\Models\VariationTemplate;

interface VariationTemplateRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<int, VariationTemplate>
     */
    public function allWithOptions(): array;
}
