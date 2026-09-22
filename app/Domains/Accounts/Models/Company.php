<?php

namespace App\Domains\Accounts\Models;

use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Contacts\Models\Address;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Metadata\Models\CustomFieldValue;
use App\Domains\Money\Models\ExchangeRateLog;
use App\Domains\Money\Models\ExchangeRateProvider;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Storage\Models\FileDisk;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Silber\Bouncer\Database\Role;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A tenant.
 *
 * Nearly every record in the application hangs off a company, and the roles
 * that grant access to those records are scoped to the company's id.
 */
class Company extends Model implements HasMedia
{
    use HasCustomFields;
    use HasFactory;
    use InteractsWithMedia;

    protected $table = 'companies';

    /**
     * A company has no `company_id`; it is the company, so its own key is
     * what its answers are scoped against.
     */
    public function customFieldCompanyId(): ?int
    {
        return $this->getKey();
    }

    protected $guarded = [
        'id',
    ];

    protected $appends = ['logo', 'logo_path'];

    /**
     * A company keeps a single branding image on the public disk.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->useDisk('public')->singleFile();
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The account holding positional ownership of this company.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Every account with a membership in this company.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_company', 'company_id', 'user_id');
    }

    /**
     * The postal address printed on this company's documents.
     */
    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    /**
     * Per-company preference rows, addressed by their `option` column.
     */
    public function settings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    /**
     * Contacts filed under this company.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Catalog entries filed under this company.
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Units of measure available to this company's catalog.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Tax rates this company can apply.
     */
    public function taxTypes(): HasMany
    {
        return $this->hasMany(TaxType::class);
    }

    /**
     * Invoices issued by this company.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Estimates issued by this company.
     */
    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }

    /**
     * Recurring invoice schedules owned by this company.
     */
    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    /**
     * Payments received by this company.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Ways this company accepts being paid.
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Expenses booked against this company.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Buckets this company sorts its expenses into.
     */
    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    /**
     * Custom field definitions declared by this company.
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    /**
     * Answers recorded for this company's custom fields.
     */
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    /**
     * Recorded exchange rate lookups for this company.
     */
    public function exchangeRateLogs(): HasMany
    {
        return $this->hasMany(ExchangeRateLog::class);
    }

    /**
     * Configured exchange rate sources for this company.
     */
    public function exchangeRateProviders(): HasMany
    {
        return $this->hasMany(ExchangeRateProvider::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * The roles defined inside this company's authorization scope.
     */
    public function getRolesAttribute()
    {
        return Role::query()->where('scope', $this->id)->get();
    }

    /**
     * Publicly reachable address of the branding image, null when none is
     * attached.
     */
    public function getLogoAttribute()
    {
        $logo = $this->logoMedia();

        return $logo ? $logo->getFullUrl() : null;
    }

    /**
     * Where the branding image lives.
     *
     * A local filesystem path while the default file disk is a system disk,
     * and a public address for every other kind of disk - the asymmetry is
     * deliberate, PDF rendering needs the path and the SPA needs the address.
     * The default disk is resolved whether or not an image is attached.
     */
    public function getLogoPathAttribute()
    {
        $logo = $this->logoMedia();

        $isSystem = FileDisk::query()->where('set_as_default', true)->first()->isSystem();

        if (! $logo) {
            return null;
        }

        return $isSystem ? $logo->getPath() : $logo->getFullUrl();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Whether any business record has been filed under this company yet.
     *
     * Contacts, catalog entries, invoices, estimates, expenses, payments and
     * recurring schedules all count; the first one found ends the search.
     */
    public function hasTransactions(): bool
    {
        $ledgers = [
            'customers',
            'items',
            'invoices',
            'estimates',
            'expenses',
            'payments',
            'recurringInvoices',
        ];

        foreach ($ledgers as $ledger) {
            if ($this->{$ledger}()->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The one media row behind the branding collection, if there is one.
     */
    private function logoMedia()
    {
        return $this->getMedia('logo')->first();
    }
}
