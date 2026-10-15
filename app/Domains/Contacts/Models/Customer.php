<?php

namespace App\Domains\Contacts\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Notifications\CustomerMailResetPasswordNotification;
use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Platform\Mail\Models\EmailLog;
use App\Support\SafeOrderBy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Silber\Bouncer\Database\HasRolesAndAbilities;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A contact owned by a company.
 *
 * The record doubles as a login for the customer portal, which is why it is an
 * authenticatable identity rather than a plain model: it can hold a password,
 * receive notifications, own an avatar in the media library and carry Bouncer
 * abilities. Relations name their foreign keys explicitly.
 */
class Customer extends Authenticatable implements HasMedia
{
    use HasApiTokens;
    use HasCustomFields;
    use HasFactory;
    use HasRolesAndAbilities;
    use InteractsWithMedia;
    use Notifiable;

    protected $table = 'customers';

    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $with = [
        'currency',
    ];

    protected $appends = [
        'formattedCreatedAt',
        'avatar',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'enable_portal' => 'boolean',
            'reminders_paused' => 'boolean',
        ];
    }

    /**
     * Company the contact was created under.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Currency every document for this contact is issued in.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * Author of the record, linked through the creator_id column.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'creator_id');
    }

    /**
     * Every postal address recorded for the contact.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'customer_id');
    }

    /**
     * The address invoices are billed to.
     */
    public function billingAddress(): HasOne
    {
        return $this->addressOfType(Address::BILLING_TYPE);
    }

    /**
     * The address goods are shipped to.
     */
    public function shippingAddress(): HasOne
    {
        return $this->addressOfType(Address::SHIPPING_TYPE);
    }

    /**
     * Estimates raised for the contact.
     */
    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class, 'customer_id');
    }

    /**
     * Invoices raised for the contact.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    /**
     * Recurring invoice schedules set up for the contact.
     */
    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class, 'customer_id');
    }

    /**
     * Payments received from the contact.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_id');
    }

    /**
     * Expenses booked against the contact.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'customer_id');
    }

    /**
     * Mail sent to the contact.
     */
    public function emailLogs(): MorphMany
    {
        return $this->morphMany(EmailLog::class, 'mailable');
    }

    /**
     * Creation date written in the company's configured date format and in the
     * language the application is running in.
     *
     * @param  mixed  $value
     */
    public function getFormattedCreatedAtAttribute($value)
    {
        $format = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->created_at)->translatedFormat($format);
    }

    /**
     * Public URL of the avatar, or the number zero when none is attached.
     */
    public function getAvatarAttribute()
    {
        $image = $this->getMedia('customer_avatar')->first();

        return $image ? asset($image->getUrl()) : 0;
    }

    /**
     * Hash a password on assignment.
     *
     * A null value is ignored so that saving the model without a password does
     * not wipe the stored hash.
     *
     * @param  mixed  $value
     */
    public function setPasswordAttribute($value)
    {
        if ($value == null) {
            return;
        }

        $this->attributes['password'] = bcrypt($value);
    }

    /**
     * Deliver a portal password reset link.
     */
    public function sendPasswordResetNotification(mixed $token): void
    {
        $notification = new CustomerMailResetPasswordNotification($token);

        $this->notify($notification);
    }

    /**
     * Return the whole result set for the sentinel limit "all", otherwise a
     * page of the requested size.
     */
    public function scopePaginateData($query, $limit)
    {
        return $limit == 'all' ? $query->get() : $query->paginate($limit);
    }

    /**
     * Narrow to the company the current request is acting on.
     */
    public function scopeWhereCompany($query)
    {
        $company = request()->header('company');

        return $query->where($this->qualifyColumn('company_id'), $company);
    }

    /**
     * Run every listed filter that carries a value.
     */
    public function scopeApplyFilters($query, array $filters)
    {
        $scopes = [
            'search' => 'whereSearch',
            'contact_name' => 'whereContactName',
            'display_name' => 'whereDisplayName',
            'customer_id' => 'whereCustomer',
            'phone' => 'wherePhone',
        ];

        foreach ($scopes as $filter => $scope) {
            $value = $filters[$filter] ?? null;

            if ($value) {
                $query->{$scope}($value);
            }
        }

        $sortField = $filters['orderByField'] ?? null;
        $sortDirection = $filters['orderBy'] ?? null;

        if ($sortField || $sortDirection) {
            $query->whereOrder($sortField ?: 'name', $sortDirection ?: 'asc');
        }
    }

    /**
     * Keep only contacts matching every whitespace-separated term, a term
     * counting as matched when it appears in the name, the email or the phone.
     */
    public function scopeWhereSearch($query, $search)
    {
        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            $query->where(function ($match) use ($term) {
                $needle = self::wildcard($term);

                $match->where('name', 'LIKE', $needle)
                    ->orWhere('email', 'LIKE', $needle)
                    ->orWhere('phone', 'LIKE', $needle);
            });
        }
    }

    /**
     * Partial match on the contact person.
     */
    public function scopeWhereContactName($query, $contactName)
    {
        return $query->where('contact_name', 'LIKE', self::wildcard($contactName));
    }

    /**
     * Partial match on the name the contact is displayed under.
     */
    public function scopeWhereDisplayName($query, $displayName)
    {
        return $query->where('name', 'LIKE', self::wildcard($displayName));
    }

    /**
     * Partial match on the phone number.
     */
    public function scopeWherePhone($query, $phone)
    {
        return $query->where('phone', 'LIKE', self::wildcard($phone));
    }

    /**
     * Pull in one specific contact.
     */
    public function scopeWhereCustomer($query, $customer_id)
    {
        $query->orWhere($this->qualifyColumn('id'), $customer_id);
    }

    /**
     * Sort by a caller-supplied column, sanitised before it reaches SQL and
     * falling back to the creation timestamp.
     */
    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        return SafeOrderBy::apply($query, $orderByField, $orderBy, 'created_at');
    }

    /**
     * Restrict to contacts invoiced inside a date range, when the caller gave
     * both ends of it.
     */
    public function scopeApplyInvoiceFilters($query, array $filters)
    {
        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? null;

        if ($from && $to) {
            $query->invoicesBetween(
                Carbon::createFromFormat('Y-m-d', $from),
                Carbon::createFromFormat('Y-m-d', $to)
            );
        }
    }

    /**
     * Restrict to contacts holding at least one invoice dated inside the
     * inclusive range.
     */
    public function scopeInvoicesBetween($query, $start, $end)
    {
        $range = [$start->format('Y-m-d'), $end->format('Y-m-d')];

        $query->whereHas('invoices', function ($invoices) use ($range) {
            $invoices->whereBetween('invoice_date', $range);
        });
    }

    /**
     * The single address the contact keeps for the given role.
     */
    private function addressOfType(string $type): HasOne
    {
        return $this->hasOne(Address::class, 'customer_id')->where('type', $type);
    }

    /**
     * Wrap a term for a substring LIKE comparison.
     */
    private static function wildcard($term): string
    {
        return '%'.$term.'%';
    }
}
