<?php

namespace App\Models;

use App\Services\InvoiceService;
use App\Support\PdfHtmlSanitizer;
use App\Traits\GeneratesPdfTrait;
use App\Traits\HasCustomFieldsTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Nwidart\Modules\Facades\Module;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Invoice extends Model implements HasMedia
{
    use GeneratesPdfTrait;
    use HasCustomFieldsTrait;
    use HasFactory;
    use InteractsWithMedia;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SENT = 'SENT';

    public const STATUS_VIEWED = 'VIEWED';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_UNPAID = 'UNPAID';

    public const STATUS_PARTIALLY_PAID = 'PARTIALLY_PAID';

    public const STATUS_PAID = 'PAID';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'invoice_date',
        'due_date',
    ];

    protected $guarded = [
        'id',
    ];

    protected $appends = [
        'formattedCreatedAt',
        'formattedInvoiceDate',
        'formattedDueDate',
        'formattedDueAmount',
        'invoicePdfUrl',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'tax' => 'integer',
            'sub_total' => 'integer',
            'discount' => 'float',
            'discount_val' => 'integer',
            'exchange_rate' => 'float',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function emailLogs(): MorphMany
    {
        return $this->morphMany('App\Models\EmailLog', 'mailable');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function getInvoicePdfUrlAttribute()
    {
        return url('/invoices/pdf/'.$this->unique_hash);
    }

    public function getPaymentModuleEnabledAttribute()
    {
        if (Module::has('Payments')) {
            return Module::isEnabled('Payments');
        }

        return false;
    }

    public function getAllowEditAttribute()
    {
        $retrospective_edit = CompanySetting::getSetting('retrospective_edits', $this->company_id);

        $allowed = true;

        $status = [
            self::STATUS_DRAFT,
            self::STATUS_SENT,
            self::STATUS_VIEWED,
            self::STATUS_COMPLETED,
        ];

        if ($retrospective_edit == 'disable_on_invoice_sent' && (in_array($this->status, $status)) && ($this->paid_status === Invoice::STATUS_PARTIALLY_PAID || $this->paid_status === Invoice::STATUS_PAID)) {
            $allowed = false;
        } elseif ($retrospective_edit == 'disable_on_invoice_partial_paid' && ($this->paid_status === Invoice::STATUS_PARTIALLY_PAID || $this->paid_status === Invoice::STATUS_PAID)) {
            $allowed = false;
        } elseif ($retrospective_edit == 'disable_on_invoice_paid' && $this->paid_status === Invoice::STATUS_PAID) {
            $allowed = false;
        }

        return $allowed;
    }

    public function getPreviousStatus()
    {
        if ($this->viewed) {
            return self::STATUS_VIEWED;
        } elseif ($this->sent) {
            return self::STATUS_SENT;
        } else {
            return self::STATUS_DRAFT;
        }
    }

    public function getFormattedNotesAttribute($value)
    {
        return $this->getNotes();
    }

    public function getFormattedCreatedAtAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    public function getFormattedDueDateAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->due_date)->translatedFormat($dateFormat);
    }

    public function getFormattedDueAmountAttribute($value)
    {
        $currency = $this->currency;

        if (! $currency) {
            $currency = Currency::findOrFail(CompanySetting::getSetting('currency', $this->company_id));
        }

        return format_money_pdf($this->due_amount, $currency);
    }

    public function getFormattedInvoiceDateAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);
        $timeFormat = CompanySetting::getSetting('carbon_time_format', $this->company_id);
        $invoiceTimeEnabled = CompanySetting::getSetting('invoice_use_time', $this->company_id);

        if ($invoiceTimeEnabled === 'YES') {
            $dateFormat .= ' '.$timeFormat;
        }

        return Carbon::parse($this->invoice_date)->translatedFormat($dateFormat);
    }

    public function scopeWhereStatus($query, $status)
    {
        return $query->where('invoices.status', $status);
    }

    public function scopeWherePaidStatus($query, $status)
    {
        return $query->where('invoices.paid_status', $status);
    }

    public function scopeWhereDueStatus($query, $status)
    {
        return $query->whereIn('invoices.paid_status', [
            self::STATUS_UNPAID,
            self::STATUS_PARTIALLY_PAID,
        ]);
    }

    public function scopeWhereInvoiceNumber($query, $invoiceNumber)
    {
        return $query->where('invoices.invoice_number', 'LIKE', '%'.$invoiceNumber.'%');
    }

    public function scopeInvoicesBetween($query, $start, $end)
    {
        return $query->whereBetween(
            'invoices.invoice_date',
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

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters)->filter()->all();

        return $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->whereSearch($search);
        })->when($filters['status'] ?? null, function ($query, $status) {
            match ($status) {
                self::STATUS_UNPAID, self::STATUS_PARTIALLY_PAID, self::STATUS_PAID => $query->wherePaidStatus($status),
                'DUE' => $query->whereDueStatus($status),
                default => $query->whereStatus($status),
            };
        })->when($filters['paid_status'] ?? null, function ($query, $paidStatus) {
            $query->wherePaidStatus($paidStatus);
        })->when($filters['invoice_id'] ?? null, function ($query, $invoiceId) {
            $query->whereInvoice($invoiceId);
        })->when($filters['invoice_number'] ?? null, function ($query, $invoiceNumber) {
            $query->whereInvoiceNumber($invoiceNumber);
        })->when(($filters['from_date'] ?? null) && ($filters['to_date'] ?? null), function ($query) use ($filters) {
            $start = Carbon::parse($filters['from_date']);
            $end = Carbon::parse($filters['to_date']);
            $query->invoicesBetween($start, $end);
        })->when($filters['customer_id'] ?? null, function ($query, $customerId) {
            $query->where('customer_id', $customerId);
        })->when($filters['orderByField'] ?? null, function ($query, $orderByField) use ($filters) {
            $orderBy = $filters['orderBy'] ?? 'desc';
            $query->orderBy($orderByField, $orderBy);
        }, function ($query) {
            $query->orderBy('sequence_number', 'desc');
        });
    }

    public function scopeWhereInvoice($query, $invoice_id)
    {
        $query->orWhere('id', $invoice_id);
    }

    public function scopeWhereCompany($query)
    {
        $query->where('invoices.company_id', request()->header('company'));
    }

    public function scopeWhereCompanyId($query, $company)
    {
        $query->where('invoices.company_id', $company);
    }

    public function scopeWhereCustomer($query, $customer_id)
    {
        $query->where('invoices.customer_id', $customer_id);
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function getPDFData()
    {
        return app(InvoiceService::class)->getPdfData($this);
    }

    public function getEmailAttachmentSetting()
    {
        $invoiceAsAttachment = CompanySetting::getSetting('invoice_email_attachment', $this->company_id);

        if ($invoiceAsAttachment == 'NO') {
            return false;
        }

        return true;
    }

    public function getCompanyAddress()
    {
        if ($this->company && (! $this->company->address()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('invoice_company_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getCustomerShippingAddress()
    {
        if ($this->customer && (! $this->customer->shippingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('invoice_shipping_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getCustomerBillingAddress()
    {
        if ($this->customer && (! $this->customer->billingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('invoice_billing_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getNotes()
    {
        return PdfHtmlSanitizer::sanitize($this->getFormattedString($this->notes));
    }

    public function getEmailString($body)
    {
        $values = array_merge($this->getFieldsArray(), $this->getExtraFields());

        $body = strtr($body, $values);

        return preg_replace('/{(.*?)}/', '', $body);
    }

    public function getExtraFields()
    {
        return [
            '{INVOICE_DATE}' => $this->formattedInvoiceDate,
            '{INVOICE_DUE_DATE}' => $this->formattedDueDate,
            '{INVOICE_NUMBER}' => $this->invoice_number,
            '{INVOICE_REF_NUMBER}' => $this->reference_number,
        ];
    }

    public function addInvoicePayment($amount)
    {
        $this->due_amount += $amount;
        $this->base_due_amount = $this->due_amount * $this->exchange_rate;

        $this->changeInvoiceStatus($this->due_amount);
    }

    public function subtractInvoicePayment($amount)
    {
        $this->due_amount -= $amount;
        $this->base_due_amount = $this->due_amount * $this->exchange_rate;

        $this->changeInvoiceStatus($this->due_amount);
    }

    /**
     * Set the invoice status from amount.
     *
     * @return array
     */
    public function getInvoiceStatusByAmount($amount)
    {
        if ($amount < 0) {
            return [];
        }

        if ($amount == 0) {
            $data = [
                'status' => Invoice::STATUS_COMPLETED,
                'paid_status' => Invoice::STATUS_PAID,
                'overdue' => false,
            ];
        } elseif ($amount == $this->total) {
            $data = [
                'status' => $this->getPreviousStatus(),
                'paid_status' => Invoice::STATUS_UNPAID,
            ];
        } else {
            $data = [
                'status' => $this->getPreviousStatus(),
                'paid_status' => Invoice::STATUS_PARTIALLY_PAID,
            ];
        }

        return $data;
    }

    /**
     * Changes the invoice status right away
     *
     * @return string[]|void
     */
    public function changeInvoiceStatus($amount)
    {
        $status = $this->getInvoiceStatusByAmount($amount);
        if (! empty($status)) {
            foreach ($status as $key => $value) {
                $this->setAttribute($key, $value);
            }
            $this->save();
        }
    }
}
