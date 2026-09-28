<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Models\BillItem;
use App\Domains\Purchases\Models\SupplierCreditItem;
use App\Domains\Taxation\Models\TaxType;

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
            foreach ($model::query()->forCompany($tax->company_id)->select('id', 'taxes')->cursor() as $line) {
                PurchaseInputs::ensure(! in_array((int) $tax->id, array_map('intval', array_column($line->taxes ?? [], 'tax_type_id')), true), 'tax_type', 'This tax is used by a bill or supplier credit. Its rates may change, but its purchase identity must be retained.');
            }
        }
    }
}
