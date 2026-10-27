{{--
    "Pay online" block: the invoice's payment link, clickable, beside a QR code
    of the same link for a printed copy. Shared by the three stock invoice
    templates, which include it inside their notes block so it takes the
    notes' margins and width and their "notes-label" heading style. Custom
    templates published into the pdf_templates namespace opt in by including
    "app.pdf.partials.payment-link" in the same place.

    Renders nothing unless getPdfData shared a link, which it does only for an
    invoice with something left to pay (InvoicePaymentLinks). `qr` is a data
    URI, so the renderer never fetches anything, and null when the company
    turned the QR code off.

    The layout styles are inline: a body-included partial cannot add rules to
    <head>, and inline styles are the one thing dompdf and Chromium honour
    identically. The link is not underlined because dompdf draws the
    underline through the middle of Noto Sans; its colour marks it instead.
--}}
@if (! empty($payment_link))
    <div style="padding-top: 24px; page-break-inside: avoid;">
        <table style="width: 100%; border-collapse: collapse;" role="presentation">
            <tr>
                <td style="vertical-align: top; padding: 0;">
                    <div class="notes-label">
                        @lang('pdf_pay_online')
                    </div>
                    <div style="margin-bottom: 6px;">
                        @lang($payment_link['qr'] ? 'pdf_pay_online_description' : 'pdf_pay_online_link_description')
                    </div>
                    <a href="{{ $payment_link['url'] }}" style="color: #5851DB; text-decoration: none; word-wrap: break-word;">{{ $payment_link['url'] }}</a>
                </td>
                @if ($payment_link['qr'])
                    <td style="width: 96px; vertical-align: top; padding: 0 0 0 16px;">
                        <img src="{{ $payment_link['qr'] }}" alt="" style="width: 96px; height: 96px;">
                    </td>
                @endif
            </tr>
        </table>
    </div>
@endif
