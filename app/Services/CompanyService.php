<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\PaymentMethod;
use App\Models\Unit;
use App\Models\User;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

class CompanyService
{
    public function setupDefaults(Company $company): bool
    {
        $this->setupRoles($company);
        $this->setupDefaultPaymentMethods($company);
        $this->setupDefaultUnits($company);
        $this->setupDefaultSettings($company);

        return true;
    }

    public function setupRoles(Company $company): void
    {
        BouncerFacade::scope()->to($company->id);

        $superAdmin = BouncerFacade::role()->firstOrCreate([
            'name' => 'super admin',
            'title' => 'Super Admin',
            'scope' => $company->id,
        ]);

        foreach (config('abilities.abilities') as $ability) {
            BouncerFacade::allow($superAdmin)->to($ability['ability'], $ability['model']);
        }
    }

    public function delete(Company $company, User $user): bool
    {
        if ($company->exchangeRateLogs()->exists()) {
            $company->exchangeRateLogs()->delete();
        }

        if ($company->exchangeRateProviders()->exists()) {
            $company->exchangeRateProviders()->delete();
        }

        if ($company->expenses()->exists()) {
            $company->expenses()->delete();
        }

        if ($company->expenseCategories()->exists()) {
            $company->expenseCategories()->delete();
        }

        if ($company->payments()->exists()) {
            $company->payments()->delete();
        }

        if ($company->paymentMethods()->exists()) {
            $company->paymentMethods()->delete();
        }

        if ($company->customFieldValues()->exists()) {
            $company->customFieldValues()->delete();
        }

        if ($company->customFields()->exists()) {
            $company->customFields()->delete();
        }

        if ($company->invoices()->exists()) {
            $company->invoices->map(function ($invoice) {
                $this->checkModelData($invoice);

                if ($invoice->transactions()->exists()) {
                    $invoice->transactions()->delete();
                }
            });

            $company->invoices()->delete();
        }

        if ($company->recurringInvoices()->exists()) {
            $company->recurringInvoices->map(function ($recurringInvoice) {
                $this->checkModelData($recurringInvoice);
            });

            $company->recurringInvoices()->delete();
        }

        if ($company->estimates()->exists()) {
            $company->estimates->map(function ($estimate) {
                $this->checkModelData($estimate);
            });

            $company->estimates()->delete();
        }

        if ($company->items()->exists()) {
            $company->items()->delete();
        }

        if ($company->taxTypes()->exists()) {
            $company->taxTypes()->delete();
        }

        if ($company->customers()->exists()) {
            $company->customers->map(function ($customer) {
                if ($customer->addresses()->exists()) {
                    $customer->addresses()->delete();
                }

                $customer->delete();
            });
        }

        $roles = Role::when($company->id, function ($query) use ($company) {
            return $query->where('scope', $company->id);
        })->get();

        if ($roles) {
            $roles->map(function ($role) {
                $role->delete();
            });
        }

        if ($company->users()->exists()) {
            $user->companies()->detach($company->id);
        }

        $company->settings()->delete();

        $company->address()->delete();

        $company->delete();

        return true;
    }

    private function setupDefaultPaymentMethods(Company $company): void
    {
        PaymentMethod::create(['name' => 'Cash', 'company_id' => $company->id]);
        PaymentMethod::create(['name' => 'Check', 'company_id' => $company->id]);
        PaymentMethod::create(['name' => 'Credit Card', 'company_id' => $company->id]);
        PaymentMethod::create(['name' => 'Bank Transfer', 'company_id' => $company->id]);
    }

    private function setupDefaultUnits(Company $company): void
    {
        Unit::create(['name' => 'box', 'company_id' => $company->id]);
        Unit::create(['name' => 'cm', 'company_id' => $company->id]);
        Unit::create(['name' => 'dz', 'company_id' => $company->id]);
        Unit::create(['name' => 'ft', 'company_id' => $company->id]);
        Unit::create(['name' => 'g', 'company_id' => $company->id]);
        Unit::create(['name' => 'in', 'company_id' => $company->id]);
        Unit::create(['name' => 'kg', 'company_id' => $company->id]);
        Unit::create(['name' => 'km', 'company_id' => $company->id]);
        Unit::create(['name' => 'lb', 'company_id' => $company->id]);
        Unit::create(['name' => 'mg', 'company_id' => $company->id]);
        Unit::create(['name' => 'pc', 'company_id' => $company->id]);
    }

    private function setupDefaultSettings(Company $company): void
    {
        $defaultInvoiceEmailBody = 'You have received a new invoice from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:';
        $defaultEstimateEmailBody = 'You have received a new estimate from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:';
        $defaultPaymentEmailBody = 'Thank you for the payment.</b></br> Please download your payment receipt using the button below:';
        $billingAddressFormat = '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_ADDRESS_STREET_2}</p><p>{BILLING_CITY}  {BILLING_STATE}</p><p>{BILLING_COUNTRY}  {BILLING_ZIP_CODE}</p><p>{BILLING_PHONE}</p>';
        $shippingAddressFormat = '<h3>{SHIPPING_ADDRESS_NAME}</h3><p>{SHIPPING_ADDRESS_STREET_1}</p><p>{SHIPPING_ADDRESS_STREET_2}</p><p>{SHIPPING_CITY}  {SHIPPING_STATE}</p><p>{SHIPPING_COUNTRY}  {SHIPPING_ZIP_CODE}</p><p>{SHIPPING_PHONE}</p>';
        $companyAddressFormat = '<h3><strong>{COMPANY_NAME}</strong></h3><p>{COMPANY_ADDRESS_STREET_1}</p><p>{COMPANY_ADDRESS_STREET_2}</p><p>{COMPANY_CITY} {COMPANY_STATE}</p><p>{COMPANY_COUNTRY}  {COMPANY_ZIP_CODE}</p><p>{COMPANY_PHONE}</p>';
        $paymentFromCustomerAddress = '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_ADDRESS_STREET_2}</p><p>{BILLING_CITY} {BILLING_STATE} {BILLING_ZIP_CODE}</p><p>{BILLING_COUNTRY}</p><p>{BILLING_PHONE}</p>';

        $settings = [
            'invoice_mail_body' => $defaultInvoiceEmailBody,
            'estimate_mail_body' => $defaultEstimateEmailBody,
            'payment_mail_body' => $defaultPaymentEmailBody,
            'invoice_company_address_format' => $companyAddressFormat,
            'invoice_shipping_address_format' => $shippingAddressFormat,
            'invoice_billing_address_format' => $billingAddressFormat,
            'estimate_company_address_format' => $companyAddressFormat,
            'estimate_shipping_address_format' => $shippingAddressFormat,
            'estimate_billing_address_format' => $billingAddressFormat,
            'payment_company_address_format' => $companyAddressFormat,
            'payment_from_customer_address_format' => $paymentFromCustomerAddress,
            'currency' => request()->currency ?? 13,
            'time_zone' => 'Asia/Kolkata',
            'language' => 'en',
            'fiscal_year' => '1-12',
            'carbon_date_format' => 'Y/m/d',
            'moment_date_format' => 'YYYY/MM/DD',
            'carbon_time_format' => 'H:i',
            'moment_time_format' => 'HH:mm',
            'invoice_use_time' => 'NO',
            'notification_email' => 'noreply@invoiceshelf.com',
            'notify_invoice_viewed' => 'NO',
            'notify_estimate_viewed' => 'NO',
            'tax_per_item' => 'NO',
            'discount_per_item' => 'NO',
            'invoice_email_attachment' => 'NO',
            'estimate_email_attachment' => 'NO',
            'payment_email_attachment' => 'NO',
            'retrospective_edits' => 'allow',
            'invoice_number_format' => '{{SERIES:INV}}{{DELIMITER:-}}{{SEQUENCE:6}}',
            'estimate_number_format' => '{{SERIES:EST}}{{DELIMITER:-}}{{SEQUENCE:6}}',
            'payment_number_format' => '{{SERIES:PAY}}{{DELIMITER:-}}{{SEQUENCE:6}}',
            'estimate_set_expiry_date_automatically' => 'YES',
            'estimate_expiry_date_days' => 7,
            'invoice_set_due_date_automatically' => 'YES',
            'invoice_due_date_days' => 7,
            'bulk_exchange_rate_configured' => 'YES',
            'estimate_convert_action' => 'no_action',
            'automatically_expire_public_links' => 'YES',
            'link_expiry_days' => 7,
        ];

        CompanySetting::setSettings($settings, $company->id);
    }

    private function checkModelData($model): void
    {
        $model->items->map(function ($item) {
            if ($item->taxes()->exists()) {
                $item->taxes()->delete();
            }

            $item->delete();
        });

        if ($model->taxes()->exists()) {
            $model->taxes()->delete();
        }
    }
}
