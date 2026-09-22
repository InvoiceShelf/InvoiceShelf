<?php

namespace App\Domains\Receivables\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Money\Models\Currency;
use App\Domains\Receivables\Contracts\PaymentPdfDataProvider;
use App\Domains\Receivables\Jobs\GeneratePaymentPdfJob;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Pdf\Concerns\GeneratesPdf;
use App\Platform\Pdf\Rendering\PdfHtmlSanitizer;
use App\Support\SafeOrderBy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Money received from a contact.
 *
 * A receipt stands on its own: it records what arrived, in the contact's
 * currency, and how much that was worth in the company's books. Which
 * documents it settles is a separate, replaceable list of slices held in
 * `payment_allocations`, so nothing here points at an invoice. Whatever is
 * left unallocated is the contact's credit.
 *
 * Saving one queues a fresh rendering of its receipt PDF, and the shareable
 * link to that file is derived from the row's unique hash.
 */
class Payment extends Model implements HasMedia
{
    use GeneratesPdf;
    use HasCustomFields;
    use HasFactory;
    use InteractsWithMedia;

    protected $table = 'payments';

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
        'payment_date',
    ];

    /**
     * Computed attributes, listed in the order they are serialized.
     *
     * @var array
     */
    protected $appends = [
        'formattedCreatedAt',
        'formattedPaymentDate',
        'paymentPdfUrl',
    ];

    /**
     * Attribute casts.
     *
     * The amounts are left alone: they are whole minor units already, and the
     * driver hands them back in the shape the rest of the domain expects. Only
     * the free-text note and the fractional exchange rate are pinned.
     */
    protected function casts(): array
    {
        return [
            'notes' => 'string',
            'exchange_rate' => 'float',
        ];
    }

    /**
     * Keep the stored receipt PDF in step with the row.
     *
     * A brand new receipt only has to be rendered; a saved one has to replace
     * the file already on disk, because the number the file is named for may
     * have moved.
     */
    protected static function booted()
    {
        static::created(function ($receipt) {
            self::queueRender($receipt, false);
        });

        static::updated(function ($receipt) {
            self::queueRender($receipt, true);
        });

        // HasCustomFields registers its clean-up from its own booted(), which
        // a class declaring this method replaces outright rather than adds to.
        // Repeated here so a deleted receipt does not leave its answers behind.
        static::deleting(function ($receipt) {
            if ($receipt->fields()->exists()) {
                $receipt->fields()->delete();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The online-payment attempt that produced this receipt, when a gateway
     * was involved.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * Mail sent about this receipt.
     */
    public function emailLogs(): MorphMany
    {
        return $this->morphMany(EmailLog::class, 'mailable');
    }

    /**
     * Contact the money came from.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Company the receipt was booked under.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * The slices this receipt has been split into across documents.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    /**
     * Documents this receipt settles, with the allocated amounts carried on
     * the pivot.
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations', 'payment_id', 'invoice_id')
            ->withPivot(['amount', 'base_amount'])
            ->withTimestamps();
    }

    /**
     * Staff account that recorded the receipt.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Currency the money arrived in, always the contact's own.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * How the money was taken, when it was recorded.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors and mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Store gateway settings as JSON.
     *
     * An empty value is skipped rather than written, so saving a receipt that
     * carries none leaves whatever was already on file untouched.
     *
     * @param  mixed  $value
     */
    public function setSettingsAttribute($value)
    {
        if (! $value) {
            return;
        }

        $encoded = json_encode($value);

        $this->attributes['settings'] = $encoded;
    }

    /**
     * Creation timestamp in the company's configured date format, written in
     * the language the application is running in.
     *
     * @param  mixed  $value
     */
    public function getFormattedCreatedAtAttribute($value)
    {
        $moment = Carbon::parse($this->created_at);

        return $moment->translatedFormat($this->receiptDateFormat());
    }

    /**
     * Date the money arrived, in the company's configured date format and in
     * the language the application is running in.
     *
     * @param  mixed  $value
     */
    public function getFormattedPaymentDateAttribute($value)
    {
        $moment = Carbon::parse($this->payment_date);

        return $moment->translatedFormat($this->receiptDateFormat());
    }

    /**
     * Shareable link to the rendered receipt. Possession of the hash is the
     * only credential the link needs.
     */
    public function getPaymentPdfUrlAttribute()
    {
        $hash = $this->unique_hash;

        return url('/payments/pdf/'.$hash);
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Run every listed filter that carries a value.
     *
     * Order is load-bearing: the clauses land in the query in the order
     * written here, and the receipt-id filter contributes an OR, which makes
     * everything queued before it part of that alternative. A filter sent as
     * an empty string, a zero or a null counts as not sent at all.
     */
    public function scopeApplyFilters($query, array $filters)
    {
        $clauses = [
            'search' => fn ($wanted) => $query->whereSearch($wanted),
            'payment_number' => fn ($wanted) => $query->paymentNumber($wanted),
            'payment_id' => fn ($wanted) => $query->wherePayment($wanted),
            'payment_method_id' => fn ($wanted) => $query->paymentMethod($wanted),
            'customer_id' => fn ($wanted) => $query->whereCustomer($wanted),
        ];

        foreach ($clauses as $filter => $clause) {
            $wanted = $filters[$filter] ?? null;

            if ($wanted) {
                $clause($wanted);
            }
        }

        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? null;

        if ($from && $to) {
            $query->paymentsBetween(
                Carbon::createFromFormat('Y-m-d', $from),
                Carbon::createFromFormat('Y-m-d', $to)
            );
        }

        $sortField = $filters['orderByField'] ?? null;
        $sortDirection = $filters['orderBy'] ?? null;

        if ($sortField || $sortDirection) {
            $query->whereOrder($sortField ?: 'sequence_number', $sortDirection ?: 'desc');
        }
    }

    /**
     * Keep only receipts whose contact matches every whitespace-separated
     * term, a term counting as matched when it turns up in the display name,
     * the contact person or the company name.
     */
    public function scopeWhereSearch($query, $search)
    {
        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            $needle = '%'.$term.'%';

            $query->whereHas('customer', function ($payer) use ($needle) {
                $payer->where('name', 'LIKE', $needle)
                    ->orWhere('contact_name', 'LIKE', $needle)
                    ->orWhere('company_name', 'LIKE', $needle);
            });
        }
    }

    /**
     * Partial match on the receipt number.
     */
    public function scopePaymentNumber($query, $paymentNumber)
    {
        return $query->where($this->qualifyColumn('payment_number'), 'LIKE', '%'.$paymentNumber.'%');
    }

    /**
     * Narrow to receipts taken one particular way.
     */
    public function scopePaymentMethod($query, $paymentMethodId)
    {
        return $query->where($this->qualifyColumn('payment_method_id'), $paymentMethodId);
    }

    /**
     * Restrict to receipts dated inside the inclusive range.
     */
    public function scopePaymentsBetween($query, $start, $end)
    {
        return $query->whereBetween($this->qualifyColumn('payment_date'), [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]);
    }

    /**
     * Sort by a caller-supplied column, sanitised before it reaches SQL and
     * falling back to the creation timestamp.
     */
    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        SafeOrderBy::apply($query, $orderByField, $orderBy, 'created_at');
    }

    /**
     * Widen a listing to also take in one specific receipt.
     *
     * The column is deliberately left unqualified: the listing query joins the
     * contacts table, so this is the clause that decides whether an id filter
     * is answerable at all.
     */
    public function scopeWherePayment($query, $payment_id)
    {
        $query->orWhere('id', $payment_id);
    }

    /**
     * Narrow to the company the current request is acting on.
     */
    public function scopeWhereCompany($query)
    {
        $company = request()->header('company');

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
        return $limit == 'all' ? $query->get() : $query->paginate($limit);
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering and correspondence
    |--------------------------------------------------------------------------
    */

    /**
     * View data for the PDF renderer.
     */
    public function getPDFData(): mixed
    {
        $provider = app(PaymentPdfDataProvider::class);

        return $provider->getPdfData($this);
    }

    /**
     * The company's address block for print, or false when the company has no
     * address on file.
     */
    public function getCompanyAddress(): string|false
    {
        return $this->addressBlock(
            $this->company && (! $this->company->address()->exists()),
            'payment_company_address_format'
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
            'payment_from_customer_address_format'
        );
    }

    /**
     * Whether outgoing mail should carry the receipt PDF. Anything other than
     * an explicit refusal counts as consent.
     */
    public function getEmailAttachmentSetting(): bool
    {
        return CompanySetting::getSetting('payment_email_attachment', $this->company_id) != 'NO';
    }

    /**
     * The note field with its placeholders resolved and its markup sanitised.
     */
    public function getNotes(): string
    {
        $resolved = $this->getFormattedString($this->notes);

        return PdfHtmlSanitizer::sanitize($resolved);
    }

    /**
     * Resolve the placeholders in a mail body, dropping any that named
     * something this receipt cannot supply.
     */
    public function getEmailBody(string $body): string
    {
        $placeholders = array_merge($this->getFieldsArray(), $this->getExtraFields());

        return preg_replace('/{(.*?)}/', '', strtr($body, $placeholders));
    }

    /**
     * The placeholders this receipt contributes on top of the shared contact
     * and company set.
     */
    public function getExtraFields(): array
    {
        $method = $this->paymentMethod;
        $money = format_money_pdf($this->amount, $this->customer->currency);

        return [
            '{PAYMENT_DATE}' => $this->formattedPaymentDate,
            '{PAYMENT_MODE}' => $method ? $method->name : null,
            '{PAYMENT_NUMBER}' => $this->payment_number,
            '{PAYMENT_AMOUNT}' => $money,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Hand a receipt's PDF rendering to the queue once the surrounding
     * transaction has actually landed, and keep the job itself from starting
     * any earlier than that.
     */
    private static function queueRender(self $payment, bool $replaceExisting): void
    {
        DB::afterCommit(function () use ($payment, $replaceExisting) {
            GeneratePaymentPdfJob::dispatch($payment, $replaceExisting)->afterCommit();
        });
    }

    /**
     * One of the printable address blocks: the company's or the contact's.
     *
     * The named format is only looked up once the address is known to be
     * there, so a missing one costs nothing.
     */
    private function addressBlock(bool $missing, string $setting): string|false
    {
        if ($missing) {
            return false;
        }

        return $this->getFormattedString(CompanySetting::getSetting($setting, $this->company_id));
    }

    /**
     * The date format this company writes its receipts in.
     */
    private function receiptDateFormat()
    {
        return CompanySetting::getSetting('carbon_date_format', $this->company_id);
    }
}
