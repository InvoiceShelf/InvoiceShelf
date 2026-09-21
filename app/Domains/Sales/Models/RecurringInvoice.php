<?php

namespace App\Domains\Sales\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Money\Models\Currency;
use App\Domains\Taxation\Models\Tax;
use App\Support\SafeOrderBy;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A standing order that mints invoices on a timetable.
 *
 * The row carries a whole invoice in template form — amounts, items and taxes,
 * kept in the same shape a real document uses — together with the cron
 * expression that decides when the next copy falls due and the limit, if any,
 * that eventually retires the schedule.
 */
class RecurringInvoice extends Model
{
    use HasCustomFields;
    use HasFactory;

    /**
     * Limit mode: keep issuing invoices indefinitely.
     */
    public const NONE = 'NONE';

    /**
     * Limit mode: stop after a set number of invoices has been issued.
     */
    public const COUNT = 'COUNT';

    /**
     * Limit mode: stop once the schedule passes its end date.
     */
    public const DATE = 'DATE';

    /**
     * The schedule has run its course and issues nothing further.
     */
    public const COMPLETED = 'COMPLETED';

    /**
     * The schedule is paused by hand.
     */
    public const ON_HOLD = 'ON_HOLD';

    /**
     * The schedule is live and due to fire on its cron expression.
     */
    public const ACTIVE = 'ACTIVE';

    protected $table = 'recurring_invoices';

    protected $guarded = [
        'id',
    ];

    /**
     * Kept from an older Eloquent generation, which read this list to cast the
     * named columns to dates. Current Eloquent ignores the property, so
     * starts_at is handed around as the plain string the driver returns.
     */
    protected $dates = [
        'starts_at',
    ];

    protected $appends = [
        'formattedCreatedAt',
        'formattedStartsAt',
        'formattedNextInvoiceAt',
        'formattedLimitDate',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'exchange_rate' => 'float',
            'send_automatically' => 'boolean',
        ];
    }

    /**
     * Invoices this schedule has produced so far.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'recurring_invoice_id');
    }

    /**
     * Taxes carried by the template, at document level.
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, 'recurring_invoice_id');
    }

    /**
     * Line items the generated invoices are built from.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'recurring_invoice_id');
    }

    /**
     * Contact every generated invoice is billed to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Company the schedule was set up under.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Author of the schedule, linked through the creator_id column.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Currency the template amounts are stated in.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * Start of the schedule, written in the company's date format and in the
     * language the application is running in.
     */
    public function getFormattedStartsAtAttribute()
    {
        return Carbon::parse($this->starts_at)->translatedFormat($this->companyDateFormat());
    }

    /**
     * The moment the next invoice is due, written in the company's date format
     * and in the language the application is running in.
     */
    public function getFormattedNextInvoiceAtAttribute()
    {
        return Carbon::parse($this->next_invoice_at)->translatedFormat($this->companyDateFormat());
    }

    /**
     * End date of a date-limited schedule, written in the company's date
     * format. Unlike the two above it is not translated.
     */
    public function getFormattedLimitDateAttribute()
    {
        return Carbon::parse($this->limit_date)->format($this->companyDateFormat());
    }

    /**
     * Creation date, written in the company's date format and untranslated.
     */
    public function getFormattedCreatedAtAttribute()
    {
        return Carbon::parse($this->created_at)->format($this->companyDateFormat());
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
     * Return the whole result set for the sentinel limit "all", otherwise a
     * page of the requested size.
     */
    public function scopePaginateData($query, $limit)
    {
        return $limit == 'all' ? $query->get() : $query->paginate($limit);
    }

    /**
     * Sort by a caller-supplied column, sanitised before it reaches SQL.
     */
    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        return SafeOrderBy::apply($query, $orderByField, $orderBy);
    }

    /**
     * Keep only schedules sitting in one lifecycle state.
     */
    public function scopeWhereStatus($query, $status)
    {
        return $query->where($this->qualifyColumn('status'), $status);
    }

    /**
     * Keep only the schedules billed to one contact.
     */
    public function scopeWhereCustomer($query, $customer_id)
    {
        return $query->where('customer_id', $customer_id);
    }

    /**
     * Keep only schedules whose start date falls inside the inclusive range.
     */
    public function scopeRecurringInvoicesStartBetween($query, $start, $end)
    {
        return $query->whereBetween('starts_at', [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]);
    }

    /**
     * Keep only schedules whose contact matches every whitespace-separated
     * term, a term counting as matched when it appears in the contact's name,
     * the contact person or the company name.
     */
    public function scopeWhereSearch($query, $search)
    {
        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            $query->whereHas('customer', function ($customer) use ($term) {
                $needle = '%'.$term.'%';

                $customer->where('name', 'LIKE', $needle)
                    ->orWhere('contact_name', 'LIKE', $needle)
                    ->orWhere('company_name', 'LIKE', $needle);
            });
        }
    }

    /**
     * Run every listed filter that carries a value.
     */
    public function scopeApplyFilters($query, array $filters)
    {
        $status = $filters['status'] ?? null;
        $search = $filters['search'] ?? null;
        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? null;
        $customer = $filters['customer_id'] ?? null;

        if ($status && $status !== 'ALL') {
            $query->whereStatus($status);
        }

        if ($search) {
            $query->whereSearch($search);
        }

        if ($from && $to) {
            $query->recurringInvoicesStartBetween(
                Carbon::createFromFormat('Y-m-d', $from),
                Carbon::createFromFormat('Y-m-d', $to)
            );
        }

        if ($customer) {
            $query->whereCustomer($customer);
        }

        $sortField = $filters['orderByField'] ?? null;
        $sortDirection = $filters['orderBy'] ?? null;

        if ($sortField || $sortDirection) {
            $query->whereOrder($sortField ?: 'created_at', $sortDirection ?: 'asc');
        }
    }

    /**
     * Retire the schedule, so that no further invoice is generated from it.
     */
    public function markStatusAsCompleted(): void
    {
        $this->status = static::COMPLETED;
        $this->save();
    }

    /**
     * The moment a cron expression next fires after the given date.
     *
     * The expression is evaluated in the caller's time zone, which is the
     * owning company's where one is known, and the answer comes back in the
     * application's zone so it can be compared with `now()` without further
     * conversion. Omitting the zone keeps the application's own, which is what
     * the two screens that preview a first run from a start date want.
     */
    public static function getNextInvoiceDate(string $frequency, string $from, ?string $timezone = null): string
    {
        $appZone = config('app.timezone', 'UTC');
        $zone = $timezone ?: $appZone;

        $next = (new CronExpression($frequency))->getNextRunDate($from, 0, false, $zone);

        return Carbon::instance($next)->setTimezone($appZone)->format('Y-m-d H:i:s');
    }

    /**
     * Move the due date on to the next occurrence.
     *
     * Counted from now, not from `starts_at`: counting from the start date
     * pinned this column to the first occurrence forever, so the figure the
     * schedule screen shows stopped being true the moment the first invoice
     * was generated. A schedule that has not started yet counts from its start
     * date instead, so it cannot come due early.
     */
    public function updateNextInvoiceDate(?string $from = null): void
    {
        $this->next_invoice_at = self::getNextInvoiceDate(
            $this->frequency,
            $this->nextRunCountsFrom($from),
            $this->companyTimeZone(),
        );

        $this->save();
    }

    /**
     * The point the next occurrence is measured from: now, unless the schedule
     * has a start date still in the future.
     */
    public function nextRunCountsFrom(?string $from = null): string
    {
        $moment = Carbon::parse($from ?: Carbon::now());

        if ($this->starts_at && Carbon::parse($this->starts_at)->greaterThan($moment)) {
            $moment = Carbon::parse($this->starts_at);
        }

        return $moment->format('Y-m-d H:i:s');
    }

    /**
     * The time zone the owning company keeps its books in.
     */
    public function companyTimeZone(): ?string
    {
        $zone = CompanySetting::getSetting('time_zone', $this->company_id);

        return $zone ?: null;
    }

    /**
     * The date format the owning company writes dates in.
     */
    private function companyDateFormat()
    {
        return CompanySetting::getSetting('carbon_date_format', $this->company_id);
    }
}
