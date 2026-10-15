<?php

namespace App\Platform\Pdf\Concerns;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Address;
use App\Platform\Operations\Models\Setting;
use App\Platform\Pdf\Application\FontService;
use App\Platform\Pdf\Application\PdfFilenameService;
use App\Platform\Pdf\Rendering\PdfHtmlSanitizer;
use App\Platform\Storage\Models\FileDisk;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

/**
 * PDF behaviour shared by the documents that can be rendered and archived:
 * invoices, estimates and payment receipts.
 *
 * The host model supplies getPDFData() and getExtraFields(), owns the media
 * collection an archive is filed under, and answers to a "<collection>_number"
 * attribute. Everything here is written against that contract.
 */
trait GeneratesPdf
{
    /**
     * Answer with the archived PDF when one is reachable, otherwise render a
     * fresh copy. Either way the document is served inline, so a browser shows
     * it rather than downloading it.
     */
    public function getGeneratedPDFOrStream($collection_name)
    {
        $archived = $this->getGeneratedPDF($collection_name);

        // file_exists() only answers for real filesystem paths, so an archive
        // held on a remote disk — for which getGeneratedPDF hands back a signed
        // URL — never satisfies this check and is rendered again instead.
        if ($archived && file_exists($archived['path'])) {
            $body = file_get_contents($archived['path']);
            $file_name = $archived['file_name'];
        } else {
            $language = CompanySetting::getSetting('language', $this->company_id);

            App::setLocale($language);
            app(FontService::class)->ensureFontsForLocale($language);

            // output(), never stream(): the drivers' stream() already hands back
            // a Response, and nesting one response inside another casts it to a
            // string, gluing the "HTTP/1.0 200 OK" preamble in front of the
            // file. Viewers only sniff the opening kilobyte for %PDF so this
            // looked healthy, while anything that validates the bytes (PDF/A
            // conformance, text extraction) choked on them.
            $body = $this->getPDFData()->output();
            $file_name = $this->{$collection_name.'_number'}.'.pdf';
        }

        $filenames = app(PdfFilenameService::class);
        $file_name = $filenames->filename($this, $collection_name);

        return response($body, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $filenames->disposition($file_name));
    }

    /**
     * Locate the archived PDF filed under a media collection.
     *
     * A local disk yields a filesystem path; every other driver yields a URL
     * that stays valid for five minutes. Anything that goes wrong on the way —
     * a media row whose disk record has since been deleted included — is
     * reported as "nothing archived" rather than raised.
     *
     * @return Collection|false
     */
    public function getGeneratedPDF($collection_name)
    {
        try {
            $archive = $this->getMedia($collection_name)->first();

            if (! $archive) {
                return false;
            }

            $file_disk = FileDisk::find($archive->custom_properties['file_disk_id']);

            if (! $file_disk) {
                return false;
            }

            $file_disk->setConfig();

            return collect([
                'path' => $file_disk->driver == 'local'
                    ? $archive->getPath()
                    : $archive->getTemporaryUrl(Carbon::now()->addMinutes(5)),
                'file_name' => $archive->file_name,
            ]);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Render the document and file the result under a media collection.
     *
     * Archiving is opt-in installation-wide; switched off, this is a no-op
     * reporting zero. Otherwise the render lands in the local temp area first,
     * is handed to the media library on the default disk, and the temp copy is
     * swept up afterwards.
     *
     * @return true|int|string true once filed, 0 when archiving is off, or the
     *                         failure message when the media library refused it
     */
    public function generatePDF($collection_name, $file_name, $deleteExistingFile = false)
    {
        if ((Setting::getSetting('save_pdf_to_disk') ?? 'NO') == 'NO') {
            return 0;
        }

        $language = CompanySetting::getSetting('language', $this->company_id);

        App::setLocale($language);
        app(FontService::class)->ensureFontsForLocale($language);

        $pdf = $this->getPDFData();

        $temp_directory = 'temp/'.$collection_name.'/'.$this->id;
        $temp_file = $temp_directory.'/temp.pdf';

        Storage::disk('local')->put($temp_file, $pdf->output());

        if ($deleteExistingFile) {
            // Note: the document id is handed over where a collection name
            // belongs, so this empties a collection nobody files anything
            // under, and the superseded archive survives. Left as it stands.
            $this->clearMediaCollection($this->id);
        }

        $default_disk = FileDisk::whereSetAsDefault(true)->first();

        if ($default_disk) {
            $default_disk->setConfig();
        }

        $temp_path = Storage::disk('local')->path($temp_file);

        try {
            $this->addMedia($temp_path)
                ->withCustomProperties(['file_disk_id' => $default_disk->id])
                ->usingFileName($file_name.'.pdf')
                ->toMediaCollection($collection_name, config('filesystems.default'));

            Storage::disk('local')->deleteDirectory($temp_directory);

            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * The placeholder map every document shares: the customer's two addresses,
     * the company and its address, the contact details, and one token per
     * custom-field slug carried by the document and by its customer.
     *
     * Where a document and its customer answer to the same slug, the customer's
     * answer is the one that survives. Every value is HTML-escaped, since the
     * result is substituted straight into markup.
     */
    public function getFieldsArray()
    {
        $customer = $this->customer;
        $shipping_address = $customer->shippingAddress ?? new Address;
        $billing_address = $customer->billingAddress ?? new Address;
        $company_address = $this->company->address ?? new Address;

        // Token suffix => the address attribute answering it. Shared by all
        // three address blocks; the name line is asked of the customer's only.
        $address_tokens = [
            'COUNTRY' => 'country_name',
            'STATE' => 'state',
            'CITY' => 'city',
            'ADDRESS_STREET_1' => 'address_street_1',
            'ADDRESS_STREET_2' => 'address_street_2',
            'PHONE' => 'phone',
            'ZIP_CODE' => 'zip',
        ];

        $fields = [];

        foreach (['SHIPPING' => $shipping_address, 'BILLING' => $billing_address] as $prefix => $address) {
            $fields['{'.$prefix.'_ADDRESS_NAME}'] = $address->name;

            foreach ($address_tokens as $token => $attribute) {
                $fields['{'.$prefix.'_'.$token.'}'] = $address->{$attribute};
            }
        }

        $fields['{COMPANY_NAME}'] = $this->company->name;

        foreach ($address_tokens as $token => $attribute) {
            $fields['{COMPANY_'.$token.'}'] = $company_address->{$attribute};
        }

        $fields['{COMPANY_VAT}'] = $this->company->vat_id;
        $fields['{COMPANY_TAX}'] = $this->company->tax_id;
        $fields['{CONTACT_DISPLAY_NAME}'] = $customer->name;
        $fields['{PRIMARY_CONTACT_NAME}'] = $customer->contact_name;
        $fields['{CONTACT_EMAIL}'] = $customer->email;
        $fields['{CONTACT_PHONE}'] = $customer->phone;
        $fields['{CONTACT_WEBSITE}'] = $customer->website;

        // The tax id token carries its own label, so a template printing it
        // does not have to translate one.
        $fields['{CONTACT_TAX_ID}'] = __('pdf_tax_id').': '.$customer->tax_id;

        foreach ([$this->fields, $customer->fields] as $answers) {
            foreach ($answers as $answer) {
                $fields['{'.$answer->customField->slug.'}'] = $answer->defaultAnswer;
            }
        }

        // The cast keeps a never-filled address line, custom field or tax id —
        // which arrives as null — out of htmlspecialchars(), where null is
        // deprecated on PHP 8.4 and fatal on 9. Every render used to emit those.
        return array_map(
            fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'),
            $fields
        );
    }

    /**
     * Resolve the placeholders in a stored format string — a note, an address
     * layout, an email body — and hand back PDF-ready markup.
     *
     * Tokens nothing answered to are dropped, along with the now-empty element
     * pairs they leave behind, and paragraphs are flattened to line breaks. The
     * result is sanitised, which also covers notes, since they arrive here too.
     */
    public function getFormattedString($format)
    {
        $placeholders = array_merge($this->getFieldsArray(), $this->getExtraFields());

        $markup = nl2br(strtr((string) $format, $placeholders));

        $markup = preg_replace('/{(.*?)}/', '', $markup);

        $markup = preg_replace("/<[^\/>]*>([\s]?)*<\/[^>]*>/", '', $markup);

        $markup = str_replace(['<p>', '</p>'], ['', '<br />'], $markup);

        // Sanitising here strips the SSRF vectors that can ride in on
        // user-supplied address fields, customer names and custom-field
        // answers, without every caller needing a wrapper of its own.
        return PdfHtmlSanitizer::sanitize($markup);
    }
}
