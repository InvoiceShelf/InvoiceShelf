<?php

namespace App\Domains\Sales\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Money\Models\Currency;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Receivables\Models\Transaction;
use App\Domains\Sales\Contracts\InvoicePdfDataProvider;
use App\Domains\Taxation\Models\Tax;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Pdf\Concerns\GeneratesPdf;
use App\Platform\Pdf\Rendering\PdfHtmlSanitizer;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use App\Support\MoneyConversion;
use App\Support\SafeOrderBy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A billing document raised against a contact.
 *
 * One table backs two kinds of document, told apart by the `type` column: an
 * ordinary invoice, and the credit note that reverses one and therefore carries
 * negative amounts and a pointer back at its original.
 *
 * Two independent axes describe where a document stands. `status` tracks how far
 * it has travelled towards the customer (draft, sent, viewed, completed) and
 * `paid_status` tracks the money (unpaid, partially paid, paid). The pair is
 * re-derived from the outstanding balance every time that balance moves, which
 * is why the two never have to be set by hand.
 *
 * Every monetary column holds integer minor units, and each has a `base_`
 * counterpart holding the same figure multiplied by the document's exchange
 * rate, so a company reporting in its own currency never has to re-convert.
 */
class Invoice extends Model implements HasMedia
{
    use GeneratesPdf;
    use HasCustomFields;
    use HasFactory;
    use InteractsWithMedia;

    /**
     * Raised but not yet handed to the customer.
     */
    public const STATUS_DRAFT = 'DRAFT';

    /**
     * Delivered to the customer.
     */
    public const STATUS_SENT = 'SENT';

    /**
     * Opened by the customer through a shared link.
     */
    public const STATUS_VIEWED = 'VIEWED';

    /**
     * Settled in full and closed.
     */
    public const STATUS_COMPLETED = 'COMPLETED';

    /**
     * Nothing has been collected yet.
     */
    public const STATUS_UNPAID = 'UNPAID';

    /**
     * Some of the balance has been collected.
     */
    public const STATUS_PARTIALLY_PAID = 'PARTIALLY_PAID';

    /**
     * The whole balance has been collected.
     */
    public const STATUS_PAID = 'PAID';

    /**
     * An ordinary, positively signed document.
     */
    public const TYPE_INVOICE = 'INVOICE';

    /**
     * A reversal of an earlier document, carrying negative amounts.
     */
    public const TYPE_CREDIT_NOTE = 'CREDIT_NOTE';

    protected $table = 'invoices';

    /**
     * Everything but the primary key may be mass assigned.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Columns the pre-cast date handling used to hydrate as instances.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'invoice_date',
        'due_date',
    ];

    /**
     * Computed attributes, listed in the order they are serialized.
     *
     * @var array
     */
    protected $appends = [
        'formattedCreatedAt',
        'formattedInvoiceDate',
        'formattedDueDate',
        'formattedDueAmount',
        'invoicePdfUrl',
    ];

    /**
     * Attribute casts.
     *
     * Amounts are whole minor units. The two figures that are genuinely
     * fractional, the percentage discount and the exchange rate, are floats.
     * The outstanding balance is deliberately absent: it is written by the
     * balance helpers below and left in whatever shape the driver hands back.
     */
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

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Ledger entries written when the document is settled.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Mail sent about this document.
     */
    public function emailLogs(): MorphMany
    {
        return $this->morphMany(EmailLog::class, 'mailable');
    }

    /**
     * Line items, snapshotted from the catalog at the time of writing.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Document-level applied taxes.
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    /**
     * Individual slices of payments booked against this document.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Payments touching this document, with the allocated amounts carried on
     * the pivot.
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')
            ->withPivot(['amount', 'base_amount'])
            ->withTimestamps();
    }

    /**
     * Currency the document was issued in.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Company the document was raised under.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Contact the document was raised for.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Schedule that generated this document, when it was not raised by hand.
     */
    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    /**
     * Staff account that raised the document.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * The document this one reverses, null on anything but a credit note.
     */
    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'related_invoice_id');
    }

    /**
     * Reversals raised against this document.
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(Invoice::class, 'related_invoice_id')
            ->where('type', self::TYPE_CREDIT_NOTE);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Whether this document reverses another one.
     */
    public function isCreditNote(): bool
    {
        return $this->type === self::TYPE_CREDIT_NOTE;
    }

    /**
     * Shareable link to the rendered PDF. Possession of the hash is the only
     * credential the link needs.
     */
    public function getInvoicePdfUrlAttribute()
    {
        return url('/invoices/pdf/'.$this->unique_hash);
    }

    /**
     * Whether the optional payments module is installed and switched on.
     */
    public function getPaymentModuleEnabledAttribute()
    {
        return Module::has('Payments') ? Module::isEnabled('Payments') : false;
    }

    /**
     * Whether the document may still be altered.
     *
     * A credited invoice is immutable: its line item ids anchor the lines of
     * every credit note that reverses it. Past that, the company's
     * retrospective-edits setting decides, tightening in three steps from
     * "sent and part paid" through "part paid" to "paid".
     */
    public function getAllowEditAttribute()
    {
        if ($this->hasCreditNotes()) {
            return false;
        }

        $mode = CompanySetting::getSetting('retrospective_edits', $this->company_id);

        $collected = $this->paid_status === self::STATUS_PARTIALLY_PAID
            || $this->paid_status === self::STATUS_PAID;

        $undelivered = [
            self::STATUS_DRAFT,
            self::STATUS_SENT,
            self::STATUS_VIEWED,
            self::STATUS_COMPLETED,
        ];

        if ($mode == 'disable_on_invoice_sent') {
            return ! (in_array($this->status, $undelivered) && $collected);
        }

        if ($mode == 'disable_on_invoice_partial_paid') {
            return ! $collected;
        }

        if ($mode == 'disable_on_invoice_paid') {
            return $this->paid_status !== self::STATUS_PAID;
        }

        return true;
    }

    /**
     * The delivery status to fall back on when a document stops being complete:
     * as far along as it had already travelled, and no further.
     */
    public function getPreviousStatus(): string
    {
        if ($this->viewed) {
            return self::STATUS_VIEWED;
        }

        if ($this->sent) {
            return self::STATUS_SENT;
        }

        return self::STATUS_DRAFT;
    }

    /**
     * The note field with its placeholders resolved and its markup sanitised.
     *
     * @param  mixed  $value
     */
    public function getFormattedNotesAttribute($value)
    {
        return $this->getNotes();
    }

    /**
     * Creation timestamp in the company's configured date format.
     *
     * @param  mixed  $value
     */
    public function getFormattedCreatedAtAttribute($value)
    {
        return Carbon::parse($this->created_at)->format($this->documentDateFormat());
    }

    /**
     * Payment deadline in the company's configured date format, written in the
     * language the application is running in.
     *
     * @param  mixed  $value
     */
    public function getFormattedDueDateAttribute($value)
    {
        return Carbon::parse($this->due_date)->translatedFormat($this->documentDateFormat());
    }

    /**
     * Outstanding balance rendered for print, in the document's currency, or
     * in the company's for a document that never got one.
     *
     * @param  mixed  $value
     */
    public function getFormattedDueAmountAttribute($value)
    {
        $currency = $this->currency ?: Currency::findOrFail(
            CompanySetting::getSetting('currency', $this->company_id)
        );

        return format_money_pdf($this->due_amount, $currency);
    }

    /**
     * Issue date in the company's configured date format, written in the
     * language the application is running in and carrying the time of day when
     * the company asked for invoices to be timestamped.
     *
     * @param  mixed  $value
     */
    public function getFormattedInvoiceDateAttribute($value)
    {
        $format = $this->documentDateFormat();

        if (CompanySetting::getSetting('invoice_use_time', $this->company_id) === 'YES') {
            $format .= ' '.CompanySetting::getSetting('carbon_time_format', $this->company_id);
        }

        return Carbon::parse($this->invoice_date)->translatedFormat($format);
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Narrow to one delivery status.
     */
    public function scopeWhereStatus($query, $status)
    {
        return $query->where($this->qualifyColumn('status'), $status);
    }

    /**
     * Narrow to one collection status.
     */
    public function scopeWherePaidStatus($query, $status)
    {
        return $query->where($this->qualifyColumn('paid_status'), $status);
    }

    /**
     * Narrow to documents with money still outstanding.
     *
     * The status argument is accepted for call-site symmetry with the other
     * status scopes and is deliberately unused: "due" is a fixed pair of
     * collection statuses, not a value to match.
     */
    public function scopeWhereDueStatus($query, $status)
    {
        return $query->whereIn($this->qualifyColumn('paid_status'), [
            self::STATUS_UNPAID,
            self::STATUS_PARTIALLY_PAID,
        ]);
    }

    /**
     * Partial match on the document number.
     */
    public function scopeWhereInvoiceNumber($query, $invoiceNumber)
    {
        return $query->where($this->qualifyColumn('invoice_number'), 'LIKE', '%'.$invoiceNumber.'%');
    }

    /**
     * Restrict to documents issued inside the inclusive range.
     */
    public function scopeInvoicesBetween($query, $start, $end)
    {
        return $query->whereBetween($this->qualifyColumn('invoice_date'), [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]);
    }

    /**
     * Keep only documents whose contact matches every whitespace-separated
     * term, a term counting as matched when it turns up in the display name,
     * the contact person or the company name.
     */
    public function scopeWhereSearch($query, $search)
    {
        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            $query->whereHas('customer', function ($contact) use ($term) {
                $needle = '%'.$term.'%';

                $contact->where('name', 'LIKE', $needle)
                    ->orWhere('contact_name', 'LIKE', $needle)
                    ->orWhere('company_name', 'LIKE', $needle);
            });
        }
    }

    /**
     * Sort by a caller-supplied column, sanitised before it reaches SQL.
     */
    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        SafeOrderBy::apply($query, $orderByField, $orderBy);
    }

    /**
     * Run every listed filter that carries a value.
     *
     * Falsy entries are dropped up front, so a filter sent as an empty string,
     * a zero or a null is the same as one that was never sent at all. Order is
     * load-bearing: the clauses land in the query in the order written here,
     * and the document-id filter contributes an OR, which makes everything
     * queued before it part of that alternative.
     */
    public function scopeApplyFilters($query, array $filters)
    {
        $filters = array_filter($filters);

        $clauses = [
            'search' => fn ($value) => $query->whereSearch($value),
            'status' => fn ($value) => match ($value) {
                self::STATUS_UNPAID, self::STATUS_PARTIALLY_PAID, self::STATUS_PAID => $query->wherePaidStatus($value),
                'DUE' => $query->whereDueStatus($value),
                default => $query->whereStatus($value),
            },
            'paid_status' => fn ($value) => $query->wherePaidStatus($value),
            'invoice_id' => fn ($value) => $query->whereInvoice($value),
            'invoice_number' => fn ($value) => $query->whereInvoiceNumber($value),
        ];

        foreach ($clauses as $filter => $clause) {
            $value = $filters[$filter] ?? null;

            if ($value) {
                $clause($value);
            }
        }

        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? null;

        if ($from && $to) {
            $query->invoicesBetween(Carbon::parse($from), Carbon::parse($to));
        }

        $contact = $filters['customer_id'] ?? null;

        if ($contact) {
            $query->where('customer_id', $contact);
        }

        $sortField = $filters['orderByField'] ?? null;

        if (! $sortField) {
            return $query->orderBy('sequence_number', 'desc');
        }

        return SafeOrderBy::apply($query, $sortField, $filters['orderBy'] ?? 'desc');
    }

    /**
     * Widen a listing to also take in one specific document.
     */
    public function scopeWhereInvoice($query, $invoice_id)
    {
        $query->orWhere('id', $invoice_id);
    }

    /**
     * Narrow to the company the current request is acting on.
     */
    public function scopeWhereCompany($query)
    {
        $query->where($this->qualifyColumn('company_id'), request()->header('company'));
    }

    /**
     * Narrow to one company.
     */
    public function scopeWhereCompanyId($query, $company)
    {
        $query->where($this->qualifyColumn('company_id'), $company);
    }

    /**
     * Narrow to one contact.
     */
    public function scopeWhereCustomer($query, $customer_id)
    {
        $query->where($this->qualifyColumn('customer_id'), $customer_id);
    }

    /**
     * Return the whole result set for the sentinel limit "all", otherwise a
     * page of the requested size.
     */
    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering and correspondence
    |--------------------------------------------------------------------------
    */

    /**
     * The estimate template matching this document's invoice template, falling
     * back to the first estimate template when there is no counterpart.
     */
    public function getEstimateTemplateName(): string
    {
        $counterpart = Str::replace('invoice', 'estimate', $this->template_name);

        // The blank image format is what keeps this cheap: asked for the
        // default one, the lister renders a base64 thumbnail of every single
        // template just to hand back a list of names.
        $available = array_column(PdfTemplateUtils::getFormattedTemplates('estimate', ''), 'name');

        return in_array($counterpart, $available) ? $counterpart : 'estimate1';
    }

    /**
     * View data for the PDF renderer.
     */
    public function getPDFData(): mixed
    {
        return app(InvoicePdfDataProvider::class)->getPdfData($this);
    }

    /**
     * Whether outgoing mail should carry the PDF. Anything other than an
     * explicit refusal counts as consent.
     */
    public function getEmailAttachmentSetting(): bool
    {
        return CompanySetting::getSetting('invoice_email_attachment', $this->company_id) != 'NO';
    }

    /**
     * The company's address block for print, or false when the company has no
     * address on file.
     */
    public function getCompanyAddress(): string|false
    {
        return $this->addressBlock(
            $this->company && (! $this->company->address()->exists()),
            'invoice_company_address_format'
        );
    }

    /**
     * The contact's delivery address block for print, or false when the
     * contact has no shipping address on file.
     */
    public function getCustomerShippingAddress(): string|false
    {
        return $this->addressBlock(
            $this->customer && (! $this->customer->shippingAddress()->exists()),
            'invoice_shipping_address_format'
        );
    }

    /**
     * The contact's billing address block for print, or false when the contact
     * has no billing address on file.
     */
    public function getCustomerBillingAddress(): string|false
    {
        return $this->addressBlock(
            $this->customer && (! $this->customer->billingAddress()->exists()),
            'invoice_billing_address_format'
        );
    }

    /**
     * The note field with its placeholders resolved and its markup sanitised.
     */
    public function getNotes(): string
    {
        return PdfHtmlSanitizer::sanitize($this->getFormattedString($this->notes));
    }

    /**
     * Resolve the placeholders in a mail body, dropping any that named
     * something this document cannot supply.
     */
    public function getEmailString(string $body): string
    {
        $placeholders = array_merge($this->getFieldsArray(), $this->getExtraFields());

        return preg_replace('/{(.*?)}/', '', strtr($body, $placeholders));
    }

    /**
     * The placeholders this document contributes on top of the shared contact
     * and company set.
     */
    public function getExtraFields(): array
    {
        return [
            '{INVOICE_DATE}' => $this->formattedInvoiceDate,
            '{INVOICE_DUE_DATE}' => $this->formattedDueDate,
            '{INVOICE_NUMBER}' => $this->invoice_number,
            '{INVOICE_REF_NUMBER}' => $this->reference_number,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Balance and status
    |--------------------------------------------------------------------------
    */

    /**
     * Grow the outstanding balance, restate it in the company's currency and
     * re-derive both statuses from where it lands.
     *
     * Growing the balance is what unwinding a collection looks like from the
     * document's side, which is why the amount is added rather than taken off.
     */
    public function addInvoicePayment(int $amount): void
    {
        $this->restateBalance($this->due_amount + $amount);
    }

    /**
     * Shrink the outstanding balance by a collected amount, restating it and
     * re-deriving both statuses the same way.
     */
    public function subtractInvoicePayment(int $amount): void
    {
        $this->restateBalance($this->due_amount - $amount);
    }

    /**
     * Work out the pair of statuses that describes a given outstanding balance.
     *
     * Nothing outstanding closes the document and clears the overdue flag; a
     * balance still standing at the full document total means not a penny has
     * arrived; anything in between is a part payment. A negative balance is
     * refused outright, and the empty array says so.
     */
    public function getInvoiceStatusByAmount(int $amount): array
    {
        if ($amount < 0) {
            return [];
        }

        if ($amount == 0) {
            return [
                'status' => self::STATUS_COMPLETED,
                'paid_status' => self::STATUS_PAID,
                'overdue' => false,
            ];
        }

        return [
            'status' => $this->getPreviousStatus(),
            'paid_status' => $amount == $this->total
                ? self::STATUS_UNPAID
                : self::STATUS_PARTIALLY_PAID,
        ];
    }

    /**
     * Apply the statuses a given outstanding balance implies and write the row
     * back straight away. A balance the derivation refuses leaves the document
     * untouched.
     */
    public function changeInvoiceStatus(int $amount): void
    {
        $changes = $this->getInvoiceStatusByAmount($amount);

        if (empty($changes)) {
            return;
        }

        foreach ($changes as $attribute => $value) {
            $this->setAttribute($attribute, $value);
        }

        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Whether any credit note reverses this invoice, answered from the loaded
     * relation when there is one so that an eager-loaded listing does not fire
     * a query per row.
     */
    private function hasCreditNotes(): bool
    {
        if ($this->relationLoaded('creditNotes')) {
            return $this->creditNotes->isNotEmpty();
        }

        return $this->creditNotes()->exists();
    }

    /**
     * Render one of the company's stored address formats, or hand back false
     * when the party it describes is present but has no address on file.
     */
    private function addressBlock(bool $missing, string $setting): string|false
    {
        if ($missing) {
            return false;
        }

        return $this->getFormattedString(CompanySetting::getSetting($setting, $this->company_id));
    }

    /**
     * Move the outstanding balance to a new figure, carry the company-currency
     * copy along with it, and let the statuses follow.
     */
    private function restateBalance(int|float $outstanding): void
    {
        $this->due_amount = $outstanding;
        $this->base_due_amount = MoneyConversion::toBaseMinor($outstanding, $this->exchange_rate);

        $this->changeInvoiceStatus($outstanding);
    }

    /**
     * The date format configured by the company that owns this document.
     */
    private function documentDateFormat(): mixed
    {
        return CompanySetting::getSetting('carbon_date_format', $this->company_id);
    }
}
