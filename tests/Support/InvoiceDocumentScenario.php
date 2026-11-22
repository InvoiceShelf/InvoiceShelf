<?php

namespace Tests\Support;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Contacts\Models\Address;
use App\Domains\Contacts\Models\Country;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Taxation\Models\Tax;
use App\Domains\Taxation\Models\TaxType;

/**
 * The invoice the e-invoicing contract is described with: a German seller
 * billing a German customer 250.00 net in two lines, one counted in hours,
 * plus 19 % VAT, 297.50 in all. tests/Fixtures/modules/invoice-document.standard.json
 * is what the module reader makes of it, and the e-invoicing module's own
 * fixtures are copies of that file.
 */
final class InvoiceDocumentScenario
{
    public static function seed(Company $company): Invoice
    {
        $germany = Country::query()->where('code', 'DE')->value('id');
        $euro = Currency::query()->where('code', 'EUR')->value('id');

        $company->update(['name' => 'Acme GmbH', 'vat_id' => 'DE123456789', 'tax_id' => '143/123/45678']);
        $company->address()->updateOrCreate([], [
            'address_street_1' => 'Marienplatz 1',
            'address_street_2' => null,
            'city' => 'München',
            'state' => 'Bayern',
            'zip' => '80331',
            'phone' => '+49 89 1234567',
            'country_id' => $germany,
        ]);
        CompanySetting::setSettings(['currency' => $euro, 'language' => 'en'], $company->id);

        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'name' => 'Muster GmbH',
            'company_name' => 'Muster GmbH',
            'contact_name' => 'Erika Muster',
            'email' => 'buchhaltung@muster.test',
            'phone' => null,
            'tax_id' => 'DE 987 654 321',
        ]);
        $customer->addresses()->create([
            'name' => 'Muster GmbH',
            'address_street_1' => 'Hauptstrasse 5',
            'city' => 'Berlin',
            'state' => 'Berlin',
            'zip' => '10115',
            'country_id' => $germany,
            'type' => Address::BILLING_TYPE,
        ]);

        Unit::query()->create(['company_id' => $company->id, 'name' => 'Hour', 'unit_code' => 'HUR']);

        $taxType = TaxType::factory()->create([
            'company_id' => $company->id,
            'name' => 'USt 19%',
            'percent' => 19,
            'tax_category_code' => 'S',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency_id' => $euro,
            'recurring_invoice_id' => null,
            'type' => Invoice::TYPE_INVOICE,
            'invoice_date' => '2026-02-01',
            'due_date' => '2026-03-01',
            'invoice_number' => 'INV-000042',
            'reference_number' => 'PO-4711',
            'notes' => '<p>Thank you for your business, {CONTACT_DISPLAY_NAME}.</p>',
            'status' => Invoice::STATUS_SENT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'tax_per_item' => 'NO',
            'discount_per_item' => 'NO',
            'tax_included' => false,
            'discount_type' => 'fixed',
            'discount' => 0,
            'discount_val' => 0,
            'exchange_rate' => 1,
            'sub_total' => 25000,
            'tax' => 4750,
            'total' => 29750,
            'due_amount' => 29750,
            'base_discount_val' => 0,
            'base_sub_total' => 25000,
            'base_tax' => 4750,
            'base_total' => 29750,
            'base_due_amount' => 29750,
        ]);

        foreach ([['Consulting', 'On site', 'hour', 10000, 1], ['Support', null, null, 7500, 2]] as [$name, $description, $unit, $price, $quantity]) {
            $invoice->items()->create([
                'company_id' => $company->id,
                'name' => $name,
                'description' => $description,
                'unit_name' => $unit,
                'price' => $price,
                'quantity' => $quantity,
                'total' => $price * $quantity,
                'discount_type' => 'fixed',
                'discount' => 0,
                'discount_val' => 0,
                'tax' => 0,
                'exchange_rate' => 1,
                'base_price' => $price,
                'base_discount_val' => 0,
                'base_tax' => 0,
                'base_total' => $price * $quantity,
            ]);
        }

        Tax::query()->create([
            'tax_type_id' => $taxType->id,
            'company_id' => $company->id,
            'currency_id' => $euro,
            'invoice_id' => $invoice->id,
            'name' => $taxType->name,
            'percent' => 19,
            'calculation_type' => 'percentage',
            'fixed_amount' => null,
            'compound_tax' => false,
            'amount' => 4750,
            'base_amount' => 4750,
        ]);

        return $invoice->fresh();
    }
}
