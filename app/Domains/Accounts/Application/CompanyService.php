<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Contracts\CompanyDataPurger;
use App\Domains\Accounts\Contracts\CompanyDefaultsProvisioner;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\RolePreset;
use App\Domains\Accounts\Models\User;
use App\Facades\Hashids;
use App\Support\Hashids\HashidConnection;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

/**
 * Everything that happens to a company either side of its working life: the
 * furniture a fresh one is handed, and the demolition of an old one.
 *
 * The reference data (payment methods, units) is provisioned through the
 * adapter; what stays here is the authorization scaffolding and the sheet of
 * default preferences a company starts out with.
 */
class CompanyService
{
    /** The role every company's creator is given. */
    private const OWNER_ROLE = RolePreset::OWNER;

    /** Mail bodies quoted into the outgoing document mails. */
    private const INVOICE_MAIL_BODY = 'You have received a new invoice from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:';

    private const ESTIMATE_MAIL_BODY = 'You have received a new estimate from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:';

    private const PAYMENT_MAIL_BODY = 'Thank you for the payment.</b></br> Please download your payment receipt using the button below:';

    /** Address blocks printed on the documents; placeholders filled at render. */
    private const BILLING_ADDRESS_FORMAT = '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_ADDRESS_STREET_2}</p><p>{BILLING_CITY}  {BILLING_STATE}</p><p>{BILLING_COUNTRY}  {BILLING_ZIP_CODE}</p><p>{BILLING_PHONE}</p>';

    private const SHIPPING_ADDRESS_FORMAT = '<h3>{SHIPPING_ADDRESS_NAME}</h3><p>{SHIPPING_ADDRESS_STREET_1}</p><p>{SHIPPING_ADDRESS_STREET_2}</p><p>{SHIPPING_CITY}  {SHIPPING_STATE}</p><p>{SHIPPING_COUNTRY}  {SHIPPING_ZIP_CODE}</p><p>{SHIPPING_PHONE}</p>';

    private const COMPANY_ADDRESS_FORMAT = '<h3><strong>{COMPANY_NAME}</strong></h3><p>{COMPANY_ADDRESS_STREET_1}</p><p>{COMPANY_ADDRESS_STREET_2}</p><p>{COMPANY_CITY} {COMPANY_STATE}</p><p>{COMPANY_COUNTRY}  {COMPANY_ZIP_CODE}</p><p>{COMPANY_PHONE}</p>';

    /** The payer block on a receipt, spaced differently from the billing one. */
    private const PAYMENT_CUSTOMER_ADDRESS_FORMAT = '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_ADDRESS_STREET_2}</p><p>{BILLING_CITY} {BILLING_STATE} {BILLING_ZIP_CODE}</p><p>{BILLING_COUNTRY}</p><p>{BILLING_PHONE}</p>';

    public function __construct(
        private readonly CompanyDefaultsProvisioner $companyDefaultsProvisioner,
        private readonly CompanyDataPurger $companyDataPurger,
        private readonly RolePresetService $rolePresets,
        private readonly AccessRevoker $accessRevoker,
    ) {}

    /**
     * Furnish a newly created company.
     *
     * Order is fixed: the owner role first, so the creator has something to be
     * assigned; then the reference data; then the preference sheet, which is
     * where the chosen currency lands.
     */
    /**
     * A new company with everything a company starts with: its public hash,
     * roles, default records and settings, and the given user as its owner.
     *
     * @param  array<string, mixed>  $attributes  the company columns; owner_id and slug default from the owner and the name
     */
    public function createFor(User $owner, array $attributes, int $currencyId): Company
    {
        $company = Company::query()->create($attributes + [
            'owner_id' => $owner->id,
            'slug' => Str::slug((string) ($attributes['name'] ?? '')),
        ]);

        $company->unique_hash = Hashids::connection(HashidConnection::Company->value)->encode($company->id);
        $company->save();

        $this->setupDefaults($company, $currencyId);
        $owner->companies()->attach($company->id);

        BouncerFacade::scope()->to($company->id);
        $owner->assign(self::OWNER_ROLE);

        return $company;
    }

    public function setupDefaults(Company $company, int $currencyId = 13): bool
    {
        $this->setupRoles($company);

        $this->companyDefaultsProvisioner->provision($company);

        $this->setupDefaultSettings($company, $currencyId);

        return true;
    }

    /**
     * Give the company its copy of every role preset: the `owner` role, which
     * holds the whole ability catalogue (a module enabled at the time
     * included), and the others the super administrator defines.
     *
     * Roles live inside a company's scope, so the scope is moved onto this
     * company first and left there for whatever the caller does next.
     */
    public function setupRoles(Company $company): void
    {
        BouncerFacade::scope()->to($company->id);

        $this->rolePresets->syncCompany($company->id);
    }

    /**
     * Wind a company up.
     *
     * The purger clears everything filed against the company first; what is
     * left here is the company's own furniture — its scoped roles, the
     * memberships pointing at it, its preferences, and the row itself. The
     * member accounts survive: only the link between them and the company is
     * cut.
     */
    public function delete(Company $company): bool
    {
        $this->companyDataPurger->purge($company);

        Role::query()
            ->when($company->id, function ($query) use ($company) {
                $query->where('scope', $company->id);
            })
            ->get()
            ->each(function ($role) {
                $role->delete();
            });

        foreach ($company->users()->pluck('users.id') as $userId) {
            $this->accessRevoker->revokeCompany((int) $userId, $company->id);
        }

        $company->users()->detach();

        $company->settings()->delete();

        $company->delete();

        return true;
    }

    /**
     * The preference sheet a company starts out with.
     *
     * Two of these are historical rather than sensible and are kept on
     * purpose: the time zone defaults to `Asia/Kolkata`, and outgoing mail is
     * addressed from the project's own no-reply address until an owner changes
     * it. `bulk_exchange_rate_configured` starts out done, which keeps fresh
     * companies out of the exchange-rate backfill.
     */
    private function setupDefaultSettings(Company $company, int $currencyId): void
    {
        CompanySetting::setSettings([
            'invoice_mail_body' => self::INVOICE_MAIL_BODY,
            'estimate_mail_body' => self::ESTIMATE_MAIL_BODY,
            'payment_mail_body' => self::PAYMENT_MAIL_BODY,
            'invoice_company_address_format' => self::COMPANY_ADDRESS_FORMAT,
            'invoice_shipping_address_format' => self::SHIPPING_ADDRESS_FORMAT,
            'invoice_billing_address_format' => self::BILLING_ADDRESS_FORMAT,
            'estimate_company_address_format' => self::COMPANY_ADDRESS_FORMAT,
            'estimate_shipping_address_format' => self::SHIPPING_ADDRESS_FORMAT,
            'estimate_billing_address_format' => self::BILLING_ADDRESS_FORMAT,
            'payment_company_address_format' => self::COMPANY_ADDRESS_FORMAT,
            'payment_from_customer_address_format' => self::PAYMENT_CUSTOMER_ADDRESS_FORMAT,
            'currency' => $currencyId,
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
            'credit_note_number_format' => '{{SERIES:CN}}{{DELIMITER:-}}{{SEQUENCE:6}}',
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
        ], $company->id);
    }
}
