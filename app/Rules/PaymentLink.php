<?php

namespace App\Rules;

use App\Domains\Sales\Application\InvoicePaymentLinks;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A payment link as an owner types it: an http or https URL that may use the
 * placeholders InvoicePaymentLinks fills in, and no others.
 *
 * The server never fetches the link, it only prints it for customers, so
 * there is no private-network check here; what matters is that it is a web
 * address and not something a browser would run (`javascript:`). Empty values
 * pass, so it composes with `nullable`.
 */
class PaymentLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        if (! is_string($value)) {
            $fail('The :attribute must be a link.');

            return;
        }

        $link = trim($value);

        preg_match_all('/\{[^{}]*\}/', $link, $matches);
        $unknown = array_diff($matches[0], InvoicePaymentLinks::PLACEHOLDERS);

        if ($unknown !== []) {
            $known = InvoicePaymentLinks::PLACEHOLDERS;
            $last = array_pop($known);

            $fail('The :attribute uses '.implode(', ', array_unique($unknown)).', which InvoiceShelf cannot fill in. Use '.implode(', ', $known).' or '.$last.'.');

            return;
        }

        $sample = strtr($link, [
            '{INVOICE_NUMBER}' => 'INV-000001',
            '{INVOICE_DUE_AMOUNT}' => '100.00',
            '{INVOICE_CURRENCY}' => 'USD',
        ]);

        if (! InvoicePaymentLinks::isWebUrl($sample)) {
            $fail('The :attribute must be a full web address starting with https:// or http://.');
        }
    }
}
