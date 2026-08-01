{{--
    Credit-note / cancellation banner, shared by the three stock invoice
    templates. Custom templates published into the pdf_templates namespace opt
    in by including "app.pdf.partials.credit-note-banner" at the top of their
    content area; they render exactly as before if they do not.

    Renders nothing at all for a regular invoice that has not been reversed, and
    reads only relations the caller already eager-loaded (getPdfData loads
    relatedInvoice + creditNotes), so it never issues a query of its own.

    Every style is inline: a body-included partial cannot add rules to <head>,
    and inline styles are the one thing dompdf and Chromium honour identically.
--}}
@if (isset($invoice) && $invoice instanceof \App\Models\Invoice)
    @php
        $bannerRelatedInvoice = $invoice->relationLoaded('relatedInvoice') ? $invoice->getRelation('relatedInvoice') : null;
        $bannerCreditNotes = $invoice->relationLoaded('creditNotes') ? $invoice->getRelation('creditNotes') : null;
        $bannerCreditNote = $bannerCreditNotes ? $bannerCreditNotes->first() : null;
    @endphp

    @if ($invoice->isCreditNote())
        <div style="clear: both; margin: 16px 30px; padding: 8px 12px; border: 1px solid #D64545; line-height: 1.3;">
            <div style="font-size: 13px; line-height: 1.3; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em; color: #B22222;">
                @lang('pdf_credit_note_label')
            </div>
            @if ($bannerRelatedInvoice)
                <div style="font-size: 10px; line-height: 1.3; color: #595959; padding-top: 4px;">
                    @lang('pdf_credit_note_reference', [
                        'number' => $bannerRelatedInvoice->invoice_number,
                        'date' => $bannerRelatedInvoice->formattedInvoiceDate,
                    ])
                </div>
            @endif
            @if ($invoice->credit_reason)
                {{-- Free text written by an operator, so it is echoed escaped
                     rather than through @lang, which does not escape. --}}
                <div style="font-size: 10px; line-height: 1.3; color: #595959; padding-top: 4px;">
                    {{ __('pdf_credit_note_reason', ['reason' => $invoice->credit_reason]) }}
                </div>
            @endif
        </div>
    @elseif ($bannerCreditNote)
        @php
            // Credit notes store negative totals, so the credited amount is the
            // negated sum. An invoice may carry several of them, and the banner
            // names every one: the reader has to be able to match the figure to
            // the documents it came from.
            $bannerCredited = -(int) $bannerCreditNotes->sum('total');
            $bannerCreditNumbers = $bannerCreditNotes->pluck('invoice_number')->implode(', ');
        @endphp

        @if ($bannerCredited >= (int) $invoice->total)
            <div style="clear: both; margin: 16px 30px; padding: 8px 12px; border: 1px solid #F59E0B; background-color: #FFFBEB; line-height: 1.3;">
                <div style="font-size: 13px; line-height: 1.3; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em; color: #92400E;">
                    @lang('pdf_cancelled_label')
                </div>
                <div style="font-size: 10px; line-height: 1.3; color: #595959; padding-top: 4px;">
                    @lang('pdf_cancelled_via_credit_note', ['number' => $bannerCreditNumbers])
                </div>
            </div>
        @else
            <div style="clear: both; margin: 16px 30px; padding: 8px 12px; border: 1px solid #F59E0B; background-color: #FFFBEB; line-height: 1.3;">
                <div style="font-size: 13px; line-height: 1.3; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em; color: #92400E;">
                    @lang('pdf_partially_credited_label')
                </div>
                {{-- format_money_pdf returns markup (the symbol carries its own
                     font-family), so this line is echoed unescaped and the
                     document numbers are escaped individually. --}}
                <div style="font-size: 10px; line-height: 1.3; color: #595959; padding-top: 4px;">
                    @lang('pdf_partially_credited_via_credit_notes', [
                        'amount' => format_money_pdf($bannerCredited, $invoice->customer->currency),
                        'numbers' => e($bannerCreditNumbers),
                    ])
                </div>
            </div>
        @endif
    @endif
@endif
