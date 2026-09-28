<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Purchases\Models\SupplierCredit;

class SupplierCreditPolicy extends PurchasePolicy
{
    protected const MODEL = SupplierCredit::class;

    protected const ABILITY = 'supplier-credit';
}
