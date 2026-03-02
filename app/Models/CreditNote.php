<?php

namespace App\Models;

use App\Jobs\GenerateCreditNotePdfJob;
use App\Mail\SendCreditNoteMail;
use App\Services\SerialNumberFormatter;
use App\Traits\GeneratesPdfTrait;
use App\Traits\HasCustomFieldsTrait;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Vinkla\Hashids\Facades\Hashids;

class CreditNote extends Model implements HasMedia
{
    use GeneratesPdfTrait;
    use HasCustomFieldsTrait;
    use HasFactory;
    use InteractsWithMedia;

    protected $dates = ['created_at', 'updated_at', 'credit_note_date'];

    protected $guarded = ['id'];

    protected $appends = [
        'formattedCreatedAt',
        'formattedCreditNoteDate',
        'creditNotePdfUrl',
    ];

    protected function casts(): array
    {
        return [
            'notes' => 'string',
            'exchange_rate' => 'float',
        ];
    }

    protected static function booted()
    {
        static::created(function ($creditNote) {
            GenerateCreditNotePdfJob::dispatch($creditNote);
        });

        static::updated(function ($creditNote) {
            GenerateCreditNotePdfJob::dispatch($creditNote, true);
        });
    }

    public function setSettingsAttribute($value)
    {
        if ($value) {
            $this->attributes['settings'] = json_encode($value);
        }
    }

    public function getFormattedCreatedAtAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->created_at)->translatedFormat($dateFormat);
    }

    public function getFormattedCreditNoteDateAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->credit_note_date)->translatedFormat($dateFormat);
    }

    public function getCreditNotePdfUrlAttribute()
    {
        return url('/credit_notes/pdf/'.$this->unique_hash);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function emailLogs(): MorphMany
    {
        return $this->morphMany('App\Models\EmailLog', 'mailable');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'creator_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function sendCreditNoteData($data)
    {
        $data['credit_note'] = $this->toArray();
        $data['user'] = $this->customer->toArray();
        $data['company'] = Company::find($this->company_id);
        $data['body'] = $this->getEmailBody($data['body']);
        $data['attach']['data'] = ($this->getEmailAttachmentSetting()) ? $this->getPDFData() : null;

        return $data;
    }

    public function send($data)
    {
        $data = $this->sendCreditNoteData($data);
        \Mail::to($data['to'])->send(new SendCreditNoteMail($data));

        return [
            'success' => true,
        ];
    }

    public static function createCreditNote($request)
    {
        $data = $request->getCreditNotePayload();

        if ($request->invoice_id) {
            $invoice = Invoice::find($request->invoice_id);
            $invoice->subtractInvoiceCreditNote($request->amount);
        }

        $creditNote = CreditNote::create($data);
        $creditNote->unique_hash = Hashids::connection(CreditNote::class)->encode($creditNote->id);

        $serial = (new SerialNumberFormatter)
            ->setModel($creditNote)
            ->setCompany($creditNote->company_id)
            ->setCustomer($creditNote->customer_id)
            ->setNextNumbers();

        $creditNote->sequence_number = $serial->nextSequenceNumber;
        $creditNote->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $creditNote->save();

        $company_currency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $creditNote['currency_id'] !== $company_currency) {
            ExchangeRateLog::addExchangeRateLog($creditNote);
        }

        $customFields = $request->customFields;

        if ($customFields) {
            $creditNote->addCustomFields($customFields);
        }

        $creditNote = CreditNote::with([
            'customer',
            'invoice',
            'fields',
        ])->find($creditNote->id);

        return $creditNote;
    }

    public function updateCreditNote($request)
    {
        $data = $request->getCreditNotePayload();

        if ($request->invoice_id && (! $this->invoice_id || $this->invoice_id !== $request->invoice_id)) {
            $invoice = Invoice::find($request->invoice_id);
            $invoice->subtractInvoiceCreditNote($request->amount);
        }

        if ($this->invoice_id && (! $request->invoice_id || $this->invoice_id !== $request->invoice_id)) {
            $invoice = Invoice::find($this->invoice_id);
            $invoice->addInvoiceCreditNote($this->amount);
        }

        if ($this->invoice_id && $this->invoice_id === $request->invoice_id && $request->amount !== $this->amount) {
            $invoice = Invoice::find($this->invoice_id);
            $invoice->addInvoiceCreditNote($this->amount);
            $invoice->subtractInvoiceCreditNote($request->amount);
        }

        $serial = (new SerialNumberFormatter)
            ->setModel($this)
            ->setCompany($this->company_id)
            ->setCustomer($request->customer_id)
            ->setModelObject($this->id)
            ->setNextNumbers();

        $data['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;
        $this->update($data);

        $company_currency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $company_currency) {
            ExchangeRateLog::addExchangeRateLog($this);
        }

        $customFields = $request->customFields;

        if ($customFields) {
            $this->updateCustomFields($customFields);
        }

        $creditNote = CreditNote::with([
            'customer',
            'invoice',
        ])
            ->find($this->id);

        return $creditNote;
    }

    public static function deleteCreditNotes($ids)
    {
        foreach ($ids as $id) {
            $creditNote = CreditNote::find($id);

            if ($creditNote->invoice_id != null) {
                $invoice = Invoice::find($creditNote->invoice_id);
                $invoice->due_amount = ((int) $invoice->due_amount + (int) $creditNote->amount);
                if ($invoice->due_amount == $invoice->total) {
                    $invoice->paid_status = Invoice::STATUS_UNPAID;
                } else {
                    $invoice->paid_status = Invoice::STATUS_PARTIALLY_PAID;
                }

                $invoice->status = $invoice->getPreviousStatus();
                $invoice->save();
            }

            $creditNote->delete();
        }

        return true;
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

    public function scopeCreditNoteNumber($query, $creditNoteNumber)
    {
        return $query->where('credit_notes.credit_note_number', 'LIKE', '%'.$creditNoteNumber.'%');
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

        if ($filters->get('credit_note_number')) {
            $query->creditNoteNumber($filters->get('credit_note_number'));
        }

        if ($filters->get('credit_note_id')) {
            $query->whereCreditNote($filters->get('credit_note_id'));
        }

        if ($filters->get('customer_id')) {
            $query->whereCustomer($filters->get('customer_id'));
        }

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->creditNotesBetween($start, $end);
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'sequence_number';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'desc';
            $query->whereOrder($field, $orderBy);
        }
    }

    public function scopeCreditNotesBetween($query, $start, $end)
    {
        return $query->whereBetween(
            'credit_notes.credit_note_date',
            [$start->format('Y-m-d'), $end->format('Y-m-d')]
        );
    }

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereCreditNote($query, $creditNote_id)
    {
        $query->orWhere('id', $creditNote_id);
    }

    public function scopeWhereCompany($query)
    {
        $query->where('credit_notes.company_id', request()->header('company'));
    }

    public function scopeWhereCustomer($query, $customer_id)
    {
        $query->where('credit_notes.customer_id', $customer_id);
    }

    public function getPDFData()
    {
        $company = Company::find($this->company_id);
        $locale = CompanySetting::getSetting('language', $company->id);

        \App::setLocale($locale);

        $logo = $company->logo_path;

        view()->share([
            'creditNote' => $this,
            'company_address' => $this->getCompanyAddress(),
            'billing_address' => $this->getCustomerBillingAddress(),
            'notes' => $this->getNotes(),
            'logo' => $logo ?? null,
        ]);

        if (request()->has('preview')) {
            return view('app.pdf.credit_note.credit_note');
        }

        return PDF::loadView('app.pdf.credit_note.credit_note');
    }

    public function getCompanyAddress()
    {
        if ($this->company && (! $this->company->address()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('credit_note_company_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getCustomerBillingAddress()
    {
        if ($this->customer && (! $this->customer->billingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('credit_note_from_customer_address_format', $this->company_id);
        return $this->getFormattedString($format);
    }

    public function getEmailAttachmentSetting()
    {
        $creditNoteAsAttachment = CompanySetting::getSetting('credit_note_email_attachment', $this->company_id);

        if ($creditNoteAsAttachment == 'NO') {
            return false;
        }

        return true;
    }

    public function getNotes()
    {
        return $this->getFormattedString($this->notes);
    }

    public function getEmailBody($body)
    {
        $values = array_merge($this->getFieldsArray(), $this->getExtraFields());

        $body = strtr($body, $values);

        return preg_replace('/{(.*?)}/', '', $body);
    }

    public function getExtraFields()
    {
        return [
            '{CREDIT_NOTE_DATE}' => $this->formattedCreditNoteDate,
            '{CREDIT_NOTE_NUMBER}' => $this->credit_note_number,
            '{CREDIT_NOTE_AMOUNT}' => format_money_pdf($this->amount, $this->customer->currency),
        ];
    }

    public static function generateCreditNote($transaction)
    {
        $invoice = Invoice::find($transaction->invoice_id);

        $serial = (new SerialNumberFormatter)
            ->setModel(new CreditNote)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setNextNumbers();

        $data['credit_note_number'] = $serial->getNextNumber();
        $data['credit_note_date'] = Carbon::now();
        $data['amount'] = $invoice->total;
        $data['invoice_id'] = $invoice->id;
        $data['customer_id'] = $invoice->customer_id;
        $data['exchange_rate'] = $invoice->exchange_rate;
        $data['base_amount'] = $data['amount'] * $data['exchange_rate'];
        $data['currency_id'] = $invoice->currency_id;
        $data['company_id'] = $invoice->company_id;
        $data['transaction_id'] = $transaction->id;

        $creditNote = CreditNote::create($data);
        $creditNote->unique_hash = Hashids::connection(CreditNote::class)->encode($creditNote->id);
        $creditNote->sequence_number = $serial->nextSequenceNumber;
        $creditNote->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $creditNote->save();

        $invoice->subtractInvoiceCreditNote($invoice->total);

        return $creditNote;
    }
}
