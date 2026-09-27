<?php

namespace App\Domains\Sales\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Money\Models\Currency;
use App\Domains\Taxation\Models\Tax;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Pdf\Concerns\GeneratesPdf;
use App\Platform\Pdf\Rendering\PdfHtmlSanitizer;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use App\Support\SafeOrderBy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An offer made to a customer, convertible into an invoice.
 *
 * The record is a snapshot: it keeps its own copy of the amounts, the line
 * items and the taxes, so later edits to the catalog or to a tax type leave
 * already-issued offers untouched. It renders to a PDF and can be mailed, which
 * is why it carries the PDF concern and a media collection. The lifecycle runs
 * DRAFT to SENT to VIEWED, with ACCEPTED, REJECTED and EXPIRED as terminals;
 * the constants below are the whole vocabulary.
 *
 * Business logic lives in the Sales services — what is kept here is the shape
 * of the record: relations, casts, query scopes and the strings the PDF and
 * mail layers ask for.
 */
abstract class SalesProposal extends Model implements HasMedia
{
    use GeneratesPdf;
    use HasCustomFields;
    use HasFactory;
    use InteractsWithMedia;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SENT = 'SENT';

    public const STATUS_VIEWED = 'VIEWED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_ACCEPTED = 'ACCEPTED';

    public const STATUS_REJECTED = 'REJECTED';

    abstract public function documentKind(): string;

    abstract protected function itemClass(): string;

    abstract protected function pdfProvider(): string;

    public function numberColumn(): string
    {
        return $this->documentKind().'_number';
    }

    public function dateColumn(): string
    {
        return $this->documentKind().'_date';
    }

    public function getDocumentNumberAttribute(): mixed
    {
        return $this->getAttribute($this->numberColumn());
    }

    public function getDocumentDateAttribute(): mixed
    {
        return $this->getAttribute($this->dateColumn());
    }

    /**
     * Everything but the primary key may be mass assigned.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Columns holding a point in time.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'expiry_date',
    ];

    /**
     * Computed attributes, listed in the order they are serialized.
     *
     * @var array
     */
    protected $appends = [
        'formattedExpiryDate',
    ];

    /**
     * Attribute casts.
     *
     * Money is held in integer minor units; the discount percentage and the
     * exchange rate are the only fractional values on the record.
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
     * Line items making up the offer.
     */
    public function items(): HasMany
    {
        return $this->hasMany($this->itemClass(), $this->documentKind().'_id', 'id');
    }

    /**
     * Document-level tax rows, as opposed to the per-item ones hanging off the
     * line items.
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, $this->documentKind().'_id', 'id');
    }

    /**
     * Contact the offer was made to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * Staff account that raised the offer.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    /**
     * Company the offer was issued under.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    /**
     * Currency the stored amounts are denominated in.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'id');
    }

    /**
     * Mail sent out for this offer, recorded through the polymorphic log.
     */
    public function emailLogs(): MorphMany
    {
        return $this->morphMany(EmailLog::class, 'mailable', 'mailable_type', 'mailable_id', 'id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Shareable PDF address. Possession of the hash is the credential, so the
     * link carries no company or customer context of its own.
     */
    public function getDocumentPdfUrlAttribute()
    {
        $path = '/'.$this->documentKind().'s/pdf/'.$this->unique_hash;

        return url($path);
    }

    /**
     * Expiry written in the company's configured date format and in the
     * language the application is running in.
     *
     * @param  mixed  $value
     */
    public function getFormattedExpiryDateAttribute($value)
    {
        $format = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->expiry_date)->translatedFormat($format);
    }

    /**
     * Issue date written the same way as the expiry above.
     *
     * @param  mixed  $value
     */
    public function getFormattedDocumentDateAttribute($value)
    {
        $format = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->documentDate)->translatedFormat($format);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Run every listed filter that carries a value.
     *
     * The order the filters are applied in is load-bearing: `estimate_id` is an
     * OR (see whereEstimate), so it widens whatever has been narrowed down to
     * that point and nothing added afterwards. Keep the sequence as it stands.
     */
    public function scopeApplyFilters($query, array $filters)
    {
        $scopes = [
            'search' => 'whereSearch',
            $this->numberColumn() => 'whereDocumentNumber',
            'status' => 'whereStatus',
            $this->documentKind().'_id' => 'whereDocument',
        ];

        foreach ($scopes as $filter => $scope) {
            $value = $filters[$filter] ?? null;

            if ($value) {
                $query->{$scope}($value);
            }
        }

        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? null;

        if ($from && $to) {
            $query->documentsBetween(
                Carbon::createFromFormat('Y-m-d', $from),
                Carbon::createFromFormat('Y-m-d', $to)
            );
        }

        $contact = $filters['customer_id'] ?? null;

        if ($contact) {
            $query->whereCustomer($contact);
        }

        $sortField = $filters['orderByField'] ?? null;
        $sortDirection = $filters['orderBy'] ?? null;

        if ($sortField || $sortDirection) {
            $query->whereOrder($sortField ?: 'sequence_number', $sortDirection ?: 'desc');
        }
    }

    /**
     * Restrict to offers issued inside the inclusive range.
     */
    public function scopeDocumentsBetween($query, $start, $end)
    {
        $range = [$start->format('Y-m-d'), $end->format('Y-m-d')];

        return $query->whereBetween($this->qualifyColumn($this->dateColumn()), $range);
    }

    /**
     * Exact match on the lifecycle status.
     */
    public function scopeWhereStatus($query, $status)
    {
        return $query->where($this->qualifyColumn('status'), $status);
    }

    /**
     * Partial match on the document number.
     */
    public function scopeWhereDocumentNumber($query, $estimateNumber)
    {
        return $query->where($this->qualifyColumn($this->numberColumn()), 'LIKE', '%'.$estimateNumber.'%');
    }

    /**
     * Pull one specific offer back into the result set.
     *
     * This is an OR against an unqualified `id`, not a narrowing filter — it
     * adds the row to whatever the other filters matched rather than
     * intersecting with them. Preserved deliberately.
     */
    public function scopeWhereDocument($query, $estimate_id)
    {
        return $query->orWhere($this->qualifyColumn('id'), $estimate_id);
    }

    /**
     * Keep only offers whose customer matches every whitespace-separated term,
     * a term counting as matched when it appears in the display name, the
     * contact person or the company name.
     */
    public function scopeWhereSearch($query, $search)
    {
        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            $needle = '%'.$term.'%';

            $query->whereHas('customer', function ($contact) use ($needle) {
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
        return SafeOrderBy::apply($query, $orderByField, $orderBy);
    }

    /**
     * Narrow to the company the current request is acting on.
     */
    public function scopeWhereCompany($query)
    {
        $active = request()->header('company');

        return $query->where($this->qualifyColumn('company_id'), $active);
    }

    /**
     * Narrow to one contact's offers.
     */
    public function scopeWhereCustomer($query, $customer_id)
    {
        return $query->where($this->qualifyColumn('customer_id'), $customer_id);
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
    | PDF and mail
    |--------------------------------------------------------------------------
    */

    /**
     * View data for the PDF template, assembled by the Sales domain.
     */
    public function getPDFData(): mixed
    {
        $provider = app($this->pdfProvider());

        return $provider->getPdfData($this);
    }

    /**
     * Issuer's postal address as the PDF wants it, or false when the company
     * has no address on file at all.
     */
    public function getCompanyAddress(): string|false
    {
        if ($this->company && ! $this->company->address()->exists()) {
            return false;
        }

        return $this->renderAddress($this->documentKind().'_company_address_format');
    }

    /**
     * Where the goods would ship, or false when the contact keeps no shipping
     * address.
     */
    public function getCustomerShippingAddress(): string|false
    {
        if ($this->customer && ! $this->customer->shippingAddress()->exists()) {
            return false;
        }

        return $this->renderAddress($this->documentKind().'_shipping_address_format');
    }

    /**
     * Where the offer would be billed, or false when the contact keeps no
     * billing address.
     */
    public function getCustomerBillingAddress(): string|false
    {
        if ($this->customer && ! $this->customer->billingAddress()->exists()) {
            return false;
        }

        return $this->renderAddress($this->documentKind().'_billing_address_format');
    }

    /**
     * The notes field with its placeholders filled in and the resulting markup
     * scrubbed before it reaches the renderer.
     */
    public function getNotes(): string
    {
        $rendered = $this->getFormattedString($this->notes);

        return PdfHtmlSanitizer::sanitize($rendered);
    }

    /**
     * Whether the PDF should ride along with the mail. Anything other than the
     * explicit opt-out means yes.
     */
    public function getEmailAttachmentSetting(): bool
    {
        $setting = CompanySetting::getSetting($this->documentKind().'_email_attachment', $this->company_id);

        return $setting != 'NO';
    }

    /**
     * Fill the placeholders in a mail body, then drop any brace token that was
     * left standing because nothing answered to it.
     */
    public function getEmailBody(string $body): string
    {
        $placeholders = array_merge($this->getFieldsArray(), $this->getExtraFields());

        $filled = strtr($body, $placeholders);

        return preg_replace('/{(.*?)}/', '', $filled);
    }

    /**
     * The placeholders this document type contributes on top of the shared
     * company/contact set.
     */
    public function getExtraFields(): array
    {
        $tokens = [
            strtoupper($this->documentKind()).'_DATE' => $this->formattedDocumentDate,
            strtoupper($this->documentKind()).'_EXPIRY_DATE' => $this->formattedExpiryDate,
            strtoupper($this->documentKind()).'_NUMBER' => $this->documentNumber,
            strtoupper($this->documentKind()).'_REF_NUMBER' => $this->reference_number,
        ];

        $fields = [];

        foreach ($tokens as $token => $value) {
            $fields['{'.$token.'}'] = $value;
        }

        return $fields;
    }

    /**
     * The invoice template that corresponds to this offer's own template.
     *
     * The two families are named in parallel, so the mapping is a word swap.
     * When the swapped name is not among the installed invoice templates the
     * first one takes over.
     */
    public function getInvoiceTemplateName(): string
    {
        $mapped = Str::replace($this->documentKind(), 'invoice', $this->template_name);

        // The second argument is the preview image format. Leaving it empty
        // stops the helper from rendering a base64 thumbnail of every template
        // when all that is wanted here are the names.
        $available = array_column(PdfTemplateUtils::getFormattedTemplates('invoice', ''), 'name');

        return in_array($mapped, $available) ? $mapped : 'invoice1';
    }

    /**
     * Render one of the company's address layouts against this document.
     */
    private function renderAddress(string $setting): string
    {
        $layout = CompanySetting::getSetting($setting, $this->company_id);

        return $this->getFormattedString($layout);
    }
}
