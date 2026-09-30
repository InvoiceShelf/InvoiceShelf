<?php

namespace App\Adapters\Accounts;

use App\Domains\Accounts\Contracts\MemberReferencesCleaner;
use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\RecurringCost;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Domains\Purchases\Models\SupplierPayment;
use App\Domains\Purchases\Models\SupplierRefund;

/**
 * Keeps the records a departing member created and forgets who created them,
 * so no `creator_id` is left pointing at a deleted account.
 */
class EloquentMemberReferencesCleaner implements MemberReferencesCleaner
{
    /**
     * Purchasing records, which the User model has no relations for.
     */
    private const PURCHASE_RECORDS = [
        Supplier::class,
        Bill::class,
        SupplierCredit::class,
        SupplierPayment::class,
        SupplierRefund::class,
        RecurringCost::class,
    ];

    public function clear(User $user): void
    {
        $authored = ['invoices', 'estimates', 'customers', 'recurringInvoices', 'expenses', 'payments', 'items'];
        foreach ($authored as $relation) {
            $user->{$relation}()->update(['creator_id' => null]);
        }

        foreach (self::PURCHASE_RECORDS as $model) {
            $model::query()->where('creator_id', $user->id)->update(['creator_id' => null]);
        }
    }
}
