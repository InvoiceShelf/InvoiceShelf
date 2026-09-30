<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Models\BillItem;
use App\Domains\Purchases\Models\SupplierCreditItem;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps a tax type that bill or supplier credit lines refer to from being
 * deleted or turned into a different kind of tax.
 */
class ProtectPurchaseTaxes
{
    public function updating(TaxType $tax): void
    {
        if ($tax->isDirty(['type', 'transaction_type'])) {
            $this->check($tax);
        }
    }

    public function deleting(TaxType $tax): void
    {
        $this->check($tax);
    }

    private function check(TaxType $tax): void
    {
        PurchaseInputs::ensure(! RecurringTemplates::useTax((int) $tax->company_id, (int) $tax->id), 'tax_type', 'purchase_tax_in_use');

        foreach ([BillItem::class, SupplierCreditItem::class] as $model) {
            $lines = $model::query()
                ->forCompany($tax->company_id)
                ->where(fn ($query) => $this->mentions($query, (int) $tax->id))
                ->select('id', 'taxes')
                ->cursor();

            foreach ($lines as $line) {
                $taxTypeIds = array_map('intval', array_column($line->taxes ?? [], 'tax_type_id'));

                PurchaseInputs::ensure(
                    ! in_array((int) $tax->id, $taxTypeIds, true),
                    'tax_type',
                    'purchase_tax_in_use',
                );
            }
        }
    }

    /**
     * Narrow the lines to those whose stored tax snapshots could name this
     * tax, with a LIKE every supported database runs; the exact check on the
     * decoded snapshots then rules out near misses such as 12 for 1.
     */
    private function mentions(Builder $query, int $taxTypeId): void
    {
        foreach (['"tax_type_id":'.$taxTypeId, '"tax_type_id":"'.$taxTypeId.'"'] as $needle) {
            $query->orWhere('taxes', 'like', '%'.$needle.'%');
        }
    }
}
