<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Models\BillItem;
use App\Domains\Purchases\Models\SupplierCreditItem;
use App\Domains\Taxation\Models\TaxType;

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
        foreach ([BillItem::class, SupplierCreditItem::class] as $model) {
            $lines = $model::query()
                ->forCompany($tax->company_id)
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
}
