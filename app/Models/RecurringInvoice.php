<?php

namespace App\Models;

use App\Traits\HasCustomFieldsTrait;
use Carbon\Carbon;
use Cron;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringInvoice extends Model
{
    use HasCustomFieldsTrait;
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected $dates = [
        'starts_at',
    ];

    public const NONE = 'NONE';

    public const COUNT = 'COUNT';

    public const DATE = 'DATE';

    public const COMPLETED = 'COMPLETED';

    public const ON_HOLD = 'ON_HOLD';

    public const ACTIVE = 'ACTIVE';

    protected $appends = [
        'formattedCreatedAt',
        'formattedStartsAt',
        'formattedNextInvoiceAt',
        'formattedLimitDate',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'float',
            'send_automatically' => 'boolean',
        ];
    }

    public function getFormattedStartsAtAttribute()
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->starts_at)->translatedFormat($dateFormat);
    }

    public function getFormattedNextInvoiceAtAttribute()
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->next_invoice_at)->translatedFormat($dateFormat);
    }

    public function getFormattedLimitDateAttribute()
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->limit_date)->format($dateFormat);
    }

    public function getFormattedCreatedAtAttribute()
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopeWhereCompany($query)
    {
        $query->where('recurring_invoices.company_id', request()->header('company'));
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereStatus($query, $status)
    {
        return $query->where('recurring_invoices.status', $status);
    }

    public function scopeWhereCustomer($query, $customer_id)
    {
        $query->where('customer_id', $customer_id);
    }

    public function scopeRecurringInvoicesStartBetween($query, $start, $end)
    {
        return $query->whereBetween(
            'starts_at',
            [$start->format('Y-m-d'), $end->format('Y-m-d')]
        );
    }

    public function scopeWhereSearch($query, $search)
    {
        foreach (explode(' ', $search) as $term) {
            $query->whereHas('customer', function ($query) use ($term) {
                $query->where('name', 'LIKE', '%'.$term.'%')
                    ->orWhere('contact_name', 'LIKE', '%'.$term.'%')
                    ->orWhere('company_name', 'LIKE', '%'.$term.'%');
            });
        }
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('status') && $filters->get('status') !== 'ALL') {
            $query->whereStatus($filters->get('status'));
        }

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->recurringInvoicesStartBetween($start, $end);
        }

        if ($filters->get('customer_id')) {
            $query->whereCustomer($filters->get('customer_id'));
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'created_at';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'asc';
            $query->whereOrder($field, $orderBy);
        }
    }

    public function markStatusAsCompleted(): void
    {
        if ($this->status == $this->status) {
            $this->status = self::COMPLETED;
            $this->save();
        }
    }

    public static function getNextInvoiceDate(string $frequency, string $starts_at): string
    {
        $cron = new Cron\CronExpression($frequency);
        $timezone = config('app.timezone', 'UTC');

        return $cron->getNextRunDate($starts_at, 0, false, $timezone)->format('Y-m-d H:i:s');
    }

    public function updateNextInvoiceDate(): void
    {
        $nextInvoiceAt = self::getNextInvoiceDate($this->frequency, $this->starts_at);

        $this->next_invoice_at = $nextInvoiceAt;
        $this->save();
    }
}
