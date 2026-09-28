<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Purchases\Models\SupplierPayment;

class SupplierPaymentPolicy extends PurchasePolicy
{
    protected const MODEL = SupplierPayment::class;

    protected const ABILITY = 'supplier-payment';
}
