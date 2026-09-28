<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Purchases\Models\Supplier;

class SupplierPolicy extends PurchasePolicy
{
    protected const MODEL = Supplier::class;

    protected const ABILITY = 'supplier';
}
