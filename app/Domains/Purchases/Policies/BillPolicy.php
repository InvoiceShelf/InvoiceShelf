<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Purchases\Models\Bill;

class BillPolicy extends PurchasePolicy
{
    protected const MODEL = Bill::class;

    protected const ABILITY = 'bill';
}
