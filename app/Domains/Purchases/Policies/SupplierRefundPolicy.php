<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Purchases\Models\SupplierRefund;

class SupplierRefundPolicy extends PurchasePolicy
{
    protected const MODEL = SupplierRefund::class;

    protected const ABILITY = 'supplier-refund';
}
