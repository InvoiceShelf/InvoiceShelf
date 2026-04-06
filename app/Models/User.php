<?php

namespace App\Models;

use App\Notifications\MailResetPasswordNotification;
use App\Traits\HasCustomFieldsTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Silber\Bouncer\Database\HasRolesAndAbilities;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens;
    use HasCustomFieldsTrait;
    use HasFactory;
    use HasRolesAndAbilities;
    use InteractsWithMedia;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
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
     * Find the user instance for the given username.
     */
    public function findForPassport(string $username): ?self
    {
        return $this->where('email', $username)->first();
    }

    public function setPasswordAttribute(string $value): void
    {
        if ($value != null) {
            $this->attributes['password'] = bcrypt($value);
        }
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super admin';
    }

    public function isSuperAdminOrAdmin(): bool
    {
        return ($this->role == 'super admin') || ($this->role == 'admin');
    }

    public static function login(object $request): bool
    {
        $remember = $request->remember;
        $email = $request->email;
        $password = $request->password;

        return \Auth::attempt(['email' => $email, 'password' => $password], $remember);
    }

    public function getFormattedCreatedAtAttribute($value)
    {
        $companyId = request()->header('company');

        if (! $companyId || ! CompanySetting::where('company_id', $companyId)->exists()) {
            $firstCompany = $this->companies()->first();
            if (! $firstCompany) {
                return Carbon::parse($this->created_at)->format('Y-m-d');
            }
            $companyId = $firstCompany->id;
        }

        $dateFormat = CompanySetting::getSetting('carbon_date_format', $companyId);

        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class, 'creator_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'creator_id');
    }

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class, 'creator_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_company', 'user_id', 'company_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'creator_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'creator_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'creator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'creator_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class, 'user_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', Address::BILLING_TYPE);
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', Address::SHIPPING_TYPE);
    }

    /**
     * Override the mail body for reset password notification mail.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new MailResetPasswordNotification($token));
    }

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereSearch($query, $search)
    {
        foreach (explode(' ', $search) as $term) {
            $query->where(function ($query) use ($term) {
                $query->where('name', 'LIKE', '%'.$term.'%')
                    ->orWhere('email', 'LIKE', '%'.$term.'%')
                    ->orWhere('phone', 'LIKE', '%'.$term.'%');
            });
        }
    }

    public function scopeWhereContactName($query, $contactName)
    {
        return $query->where('contact_name', 'LIKE', '%'.$contactName.'%');
    }

    public function scopeWhereDisplayName($query, $displayName)
    {
        return $query->where('name', 'LIKE', '%'.$displayName.'%');
    }

    public function scopeWherePhone($query, $phone)
    {
        return $query->where('phone', 'LIKE', '%'.$phone.'%');
    }

    public function scopeWhereEmail($query, $email)
    {
        return $query->where('email', 'LIKE', '%'.$email.'%');
    }

    public function scopeWhereCompany($query)
    {
        return $query->whereHas('companies', function ($q) {
            $q->where('company_id', request()->header('company'));
        });
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('display_name')) {
            $query->whereDisplayName($filters->get('display_name'));
        }

        if ($filters->get('email')) {
            $query->whereEmail($filters->get('email'));
        }

        if ($filters->get('phone')) {
            $query->wherePhone($filters->get('phone'));
        }

        if ($filters->get('role')) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('roles.id', $filters->get('role'));
            });
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'name';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'asc';
            $query->whereOrder($field, $orderBy);
        }
    }

    public function scopeWhereSuperAdmin($query)
    {
        $query->orWhere('role', 'super admin');
    }

    public function scopeApplyInvoiceFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->invoicesBetween($start, $end);
        }
    }

    public function scopeInvoicesBetween($query, $start, $end)
    {
        $query->whereHas('invoices', function ($query) use ($start, $end) {
            $query->whereBetween(
                'invoice_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            );
        });
    }

    public function getAvatarAttribute()
    {
        $avatar = $this->getMedia('admin_avatar')->first();

        if ($avatar) {
            return asset($avatar->getUrl());
        }

        return 0;
    }

    /**
     * Bulk upsert user settings, creating or updating each key-value pair.
     */
    public function setSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->settings()->updateOrCreate(
                [
                    'key' => $key,
                ],
                [
                    'key' => $key,
                    'value' => $value,
                ]
            );
        }
    }

    public function hasCompany(int $company_id): bool
    {
        $companies = $this->companies()->pluck('company_id')->toArray();

        return in_array($company_id, $companies);
    }

    public function getAllSettings(): Collection
    {
        return $this->settings()->get()->mapWithKeys(function ($item) {
            return [$item['key'] => $item['value']];
        });
    }

    public function getSettings(array $settings): Collection
    {
        return $this->settings()->whereIn('key', $settings)->get()->mapWithKeys(function ($item) {
            return [$item['key'] => $item['value']];
        });
    }

    /**
     * Determine whether the user is the owner of the current company.
     */
    public function isOwner(): bool
    {
        if (Schema::hasColumn('companies', 'owner_id')) {
            $company = Company::find(request()->header('company'));

            if ($company && $this->id == $company->owner_id) {
                return true;
            }
        } else {
            return $this->role == 'super admin' || $this->role == 'admin';
        }

        return false;
    }

    /**
     * Check whether the user has the required permissions based on ability data,
     * considering super-admin status, company ownership, and Bouncer abilities.
     */
    public function checkAccess(object $data): bool
    {
        if (! empty($data->data['super_admin_only']) && $data->data['super_admin_only']) {
            return $this->isSuperAdmin();
        }

        if ($this->isOwner()) {
            return true;
        }

        if ((! $data->data['owner_only']) && empty($data->data['ability'])) {
            return true;
        }

        if ((! $data->data['owner_only']) && (! empty($data->data['ability'])) && (! empty($data->data['model'])) && $this->can($data->data['ability'], $data->data['model'])) {
            return true;
        }

        if ((! $data->data['owner_only']) && $this->can($data->data['ability'])) {
            return true;
        }

        return false;
    }
}
