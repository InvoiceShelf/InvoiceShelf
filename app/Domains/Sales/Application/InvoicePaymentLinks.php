<?php

namespace App\Domains\Sales\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Sales\Models\Invoice;

/**
 * Where a customer can pay an invoice online, if anywhere.
 *
 * A payment link is a URL the company already has from its payment provider:
 * a Stripe or PayPal payment link, a Wise or Payoneer payment request. The
 * company sets one for all its invoices (`invoice_payment_link`), an invoice
 * may carry its own (`invoices.payment_link`), and the invoice's wins. Either
 * may name the invoice through the placeholders below, so one link serves
 * every invoice. InvoiceShelf only points at the link: it never learns whether
 * the customer paid, so payments are still recorded by hand.
 *
 * This is the one place that answers "where does Pay go", which is what the
 * PDF, the invoice email, the public page and the customer portal all ask. A
 * payment gateway in the customer portal (specs/payments-online.md) answers it
 * here too, rather than in each of them.
 */
class InvoicePaymentLinks
{
    /**
     * The company setting that holds the default link.
     */
    public const SETTING = 'invoice_payment_link';

    /**
     * The company setting that says whether the PDF prints a QR code of the
     * link. `NO` turns it off; anything else, including no row, leaves it on.
     */
    public const QR_SETTING = 'invoice_payment_link_qr';

    /**
     * The placeholders a link may use. Values are URL-encoded where they are
     * filled in, and amounts are plain decimals with a point and no grouping
     * (1250.00), since that is what a URL carries.
     */
    public const PLACEHOLDERS = [
        '{INVOICE_NUMBER}',
        '{INVOICE_DUE_AMOUNT}',
        '{INVOICE_CURRENCY}',
    ];

    /**
     * The longest link accepted, in characters.
     */
    public const MAX_LENGTH = 2048;

    /**
     * The link that pays this invoice now, or null when there is none or
     * nothing is left to pay.
     *
     * A credit note is never paid by its customer, and an invoice with no
     * balance has nothing to collect, so neither gets a link. A stored link
     * that does not come out as an http or https URL is ignored rather than
     * printed: validation keeps those out, and this keeps out anything that
     * reached the database some other way.
     */
    public function forInvoice(Invoice $invoice): ?string
    {
        if ($invoice->isCreditNote() || (int) $invoice->due_amount <= 0) {
            return null;
        }

        $link = $this->linkFor($invoice);

        if ($link === null) {
            return null;
        }

        $url = strtr($link, [
            '{INVOICE_NUMBER}' => rawurlencode((string) $invoice->invoice_number),
            '{INVOICE_DUE_AMOUNT}' => $this->decimal((int) $invoice->due_amount, $invoice->currency?->precision),
            '{INVOICE_CURRENCY}' => rawurlencode((string) $invoice->currency?->code),
        ]);

        return self::isWebUrl($url) ? $url : null;
    }

    /**
     * Whether the company's PDFs print a QR code beside the link.
     */
    public function printsQrCode(int|string $companyId): bool
    {
        return CompanySetting::getSetting(self::QR_SETTING, $companyId) !== 'NO';
    }

    /**
     * Whether a link, once its placeholders are filled, is something a
     * browser can open: an absolute http or https URL with a host, no
     * whitespace and no placeholder left over. Anything else (`javascript:`,
     * `data:`, a relative path) is refused, because the link is printed as a
     * button customers click.
     */
    public static function isWebUrl(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > self::MAX_LENGTH || preg_match('/[\s{}]/u', $url) === 1) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && (string) parse_url($url, PHP_URL_HOST) !== ''
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * The invoice's own link, else its company's.
     */
    private function linkFor(Invoice $invoice): ?string
    {
        $own = trim((string) $invoice->payment_link);

        if ($own !== '') {
            return $own;
        }

        $company = trim((string) CompanySetting::getSetting(self::SETTING, $invoice->company_id));

        return $company === '' ? null : $company;
    }

    /**
     * Minor units as a plain decimal in the currency's precision: 125000 is
     * "1250.00" in euros and "1250" in yen.
     */
    private function decimal(int $minor, ?int $precision): string
    {
        return number_format($minor / 100, $precision ?? 2, '.', '');
    }
}
