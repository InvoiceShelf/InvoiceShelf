<?php

namespace App\Domains\Accounts\Models;

use App\Domains\Accounts\Notifications\MailResetPasswordNotification;
use App\Domains\Catalog\Models\Item;
use App\Domains\Contacts\Models\Address;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Support\SafeOrderBy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Silber\Bouncer\Database\HasRolesAndAbilities;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A staff account.
 *
 * Staff reach the application through one or more companies joined by the
 * `user_company` pivot, and what they may do inside each of them comes from
 * Bouncer roles scoped to that company. The pre-Bouncer `role` column has one
 * job left: flagging the platform administrator. Ownership is positional, read
 * from the active company's owner column rather than from any flag stored here.
 */
class User extends Authenticatable implements HasMedia
{
    use HasApiTokens;
    use HasCustomFields;
    use HasFactory;
    use HasRolesAndAbilities;
    use InteractsWithMedia;
    use Notifiable;

    protected $table = 'users';

    /**
     * Everything but the primary key may be mass assigned.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Secrets stripped from every serialized payload.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array
     */
    protected $with = [
        'currency',
    ];

    /**
     * Computed attributes, listed in the order they are serialized.
     *
     * @var array
     */
    protected $appends = [
        'formattedCreatedAt',
        'avatar',
    ];

    /**
     * A staff account keeps a single avatar image on the public disk.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('admin_avatar')->useDisk('public')->singleFile();
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Preferred currency, eager loaded on every query.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * Whoever created this account, when it was not self-registered.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Companies this account is a member of.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_company', 'user_id', 'company_id');
    }

    /**
     * Per-user preference rows, addressed by their `key` column.
     */
    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class, 'user_id');
    }

    /**
     * Contacts this account authored.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'creator_id');
    }

    /**
     * Catalog entries this account authored.
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'creator_id');
    }

    /**
     * Estimates this account authored.
     */
    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class, 'creator_id');
    }

    /**
     * Invoices this account authored.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'creator_id');
    }

    /**
     * Recurring invoice schedules this account authored.
     */
    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class, 'creator_id');
    }

    /**
     * Payments this account recorded.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'creator_id');
    }

    /**
     * Expenses this account recorded.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'creator_id');
    }

    /**
     * Every postal address filed against this account.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * The address flagged for billing.
     */
    public function billingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', Address::BILLING_TYPE);
    }

    /**
     * The address flagged for shipping.
     */
    public function shippingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', Address::SHIPPING_TYPE);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors and mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Hash a password on assignment.
     *
     * A blank value is skipped so that saving a form which left the field empty
     * keeps the hash already on file.
     */
    public function setPasswordAttribute(string $value): void
    {
        if ($value === '') {
            return;
        }

        $this->attributes['password'] = bcrypt($value);
    }

    /**
     * Public URL of the avatar, or the number zero when none is attached.
     */
    public function getAvatarAttribute()
    {
        $image = $this->getMedia('admin_avatar')->first();

        return $image ? asset($image->getUrl()) : 0;
    }

    /**
     * Signup timestamp rendered with the date format of the company the
     * request is acting on.
     */
    public function getFormattedCreatedAtAttribute($value)
    {
        return Carbon::parse($this->created_at)->format($this->contextDateFormat());
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Sort by a caller-supplied column, sanitised before it reaches SQL.
     */
    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        return SafeOrderBy::apply($query, $orderByField, $orderBy, 'created_at');
    }

    /**
     * Keep only accounts matching every whitespace-separated term, a term
     * counting as matched when it turns up in the name, the email or the phone.
     */
    public function scopeWhereSearch($query, $search)
    {
        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            $needle = self::wildcard($term);

            $query->where(function ($match) use ($needle) {
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
     * Partial match on the name the account is displayed under.
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
     * Partial match on the email address.
     */
    public function scopeWhereEmail($query, $email)
    {
        return $query->where('email', 'LIKE', self::wildcard($email));
    }

    /**
     * Keep only members of the company the request is acting on.
     */
    public function scopeWhereCompany($query)
    {
        $company = request()->header('company');

        return $query->whereHas('companies', function ($membership) use ($company) {
            $membership->where('company_id', $company);
        });
    }

    /**
     * Widen a listing to also take in the platform administrator.
     */
    public function scopeWhereSuperAdmin($query)
    {
        $query->orWhere('role', 'super admin');
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
     * Run every listed filter that carries a value.
     */
    public function scopeApplyFilters($query, array $filters)
    {
        $scopes = [
            'search' => 'whereSearch',
            'display_name' => 'whereDisplayName',
            'email' => 'whereEmail',
            'phone' => 'wherePhone',
        ];

        foreach ($scopes as $filter => $scope) {
            $value = $filters[$filter] ?? null;

            if ($value) {
                $query->{$scope}($value);
            }
        }

        $role = $filters['role'] ?? null;

        if ($role) {
            $query->whereHas('roles', function ($assigned) use ($role) {
                $assigned->where('roles.id', $role);
            });
        }

        $sortField = $filters['orderByField'] ?? null;
        $sortDirection = $filters['orderBy'] ?? null;

        if ($sortField || $sortDirection) {
            $query->whereOrder($sortField ?: 'name', $sortDirection ?: 'asc');
        }
    }

    /**
     * Restrict to accounts who authored an invoice inside a date range, when
     * the caller supplied both ends of it.
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
     * Restrict to accounts holding at least one invoice dated inside the
     * inclusive range.
     */
    public function scopeInvoicesBetween($query, $start, $end)
    {
        $range = [$start->format('Y-m-d'), $end->format('Y-m-d')];

        $query->whereHas('invoices', function ($invoices) use ($range) {
            $invoices->whereBetween('invoice_date', $range);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */

    /**
     * Write a batch of preferences, replacing the value of any key already on
     * file and inserting the rest.
     */
    public function setSettings(array $settings): void
    {
        foreach ($settings as $option => $value) {
            $this->settings()->updateOrCreate(['key' => $option], ['key' => $option, 'value' => $value]);
        }
    }

    /**
     * Every preference on file for this account, keyed by setting name.
     */
    public function getAllSettings(): Collection
    {
        return $this->flattenSettings($this->settings()->get());
    }

    /**
     * The named preferences only; keys with no row on file are left out.
     */
    public function getSettings(array $settings): Collection
    {
        return $this->flattenSettings($this->settings()->whereIn('key', $settings)->get());
    }

    /*
    |--------------------------------------------------------------------------
    | Identity and access
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve an account from the identifier a token grant was asked for.
     */
    public function findForPassport(string $username): ?self
    {
        return $this->newQuery()->where('email', $username)->first();
    }

    /**
     * Start a session from a request-like object carrying the credentials.
     */
    public static function login(object $request): bool
    {
        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        return Auth::attempt($credentials, $request->remember);
    }

    /**
     * Deliver a password reset link pointing at the SPA reset screen.
     */
    public function sendPasswordResetNotification($token)
    {
        $notification = new MailResetPasswordNotification($token);

        $this->notify($notification);
    }

    /**
     * Whether this account is the platform administrator.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super admin';
    }

    /**
     * Whether the pre-Bouncer `role` column marks this account as privileged.
     */
    public function isSuperAdminOrAdmin(): bool
    {
        return $this->hasLegacyAdminRole();
    }

    /**
     * Whether this account is a member of the given company.
     */
    public function hasCompany(int $company_id): bool
    {
        return $this->companies()->pluck('company_id')->contains($company_id);
    }

    /**
     * Whether this account owns the company the request is acting on.
     *
     * Ownership is positional: it is read from the company's owner column, so
     * transferring ownership flips authorization immediately. Installs that
     * have not run the migration adding that column fall back to the old role
     * strings.
     */
    public function isOwner(): bool
    {
        if (! Schema::hasColumn('companies', 'owner_id')) {
            return $this->hasLegacyAdminRole();
        }

        $active = Company::find(request()->header('company'));

        return $active && $this->id == $active->owner_id;
    }

    /**
     * Decide whether a navigation entry's requirements are met.
     *
     * Entries reserved for the platform administrator are settled by that gate
     * alone. Owners of the active company clear everything else. Everyone else
     * needs the named ability, checked against the entry's subject model first
     * and against the bare ability afterwards; an entry naming no ability at
     * all is open.
     */
    public function checkAccess(object $data): bool
    {
        $meta = $data->data;

        if (! empty($meta['hidden'])) {
            return false;
        }

        if (! empty($meta['super_admin_only'])) {
            return $this->isSuperAdmin();
        }

        if ($this->isOwner()) {
            return true;
        }

        if ($meta['owner_only']) {
            return false;
        }

        if (empty($meta['ability'])) {
            return true;
        }

        if (! empty($meta['model']) && $this->can($meta['ability'], $meta['model'])) {
            return true;
        }

        return $this->can($meta['ability']);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Date format of the company the request is acting on, falling back to the
     * first company this account belongs to and to an ISO-style date when it
     * belongs to none.
     */
    private function contextDateFormat(): mixed
    {
        $scope = request()->header('company');

        $configured = $scope
            && CompanySetting::query()->where('company_id', $scope)->exists();

        if (! $configured) {
            $home = $this->companies()->first();

            if (! $home) {
                return 'Y-m-d';
            }

            $scope = $home->id;
        }

        return CompanySetting::getSetting('carbon_date_format', $scope);
    }

    /**
     * The pre-Bouncer administrator test, kept for installs whose companies
     * table has not gained its ownership column yet.
     */
    private function hasLegacyAdminRole(): bool
    {
        return in_array($this->role, ['super admin', 'admin']);
    }

    /**
     * Reduce preference rows to a name => value collection.
     */
    private function flattenSettings(Collection $rows): Collection
    {
        return $rows->mapWithKeys(function ($row) {
            return [$row['key'] => $row['value']];
        });
    }

    /**
     * Wrap a term for a substring LIKE comparison.
     */
    private static function wildcard($term): string
    {
        return '%'.$term.'%';
    }
}
