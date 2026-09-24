<?php

namespace Modules\PurchaseOrders\Repositories;

use App\Support\Repository\BaseRepository;
use Modules\PurchaseOrders\Models\PurchaseOrder;

class PurchaseOrderRepository extends BaseRepository implements PurchaseOrderRepositoryInterface
{
    public function __construct(PurchaseOrder $model)
    {
        parent::__construct($model);
    }
}
