<?php

namespace App\Domains\Sales\Models;

use App\Domains\Sales\Contracts\QuotePdfDataProvider;

class Quote extends SalesProposal
{
    protected $table = 'quotes';

    protected $appends = ['formattedExpiryDate', 'formattedQuoteDate', 'quotePdfUrl'];

    public function documentKind(): string
    {
        return 'quote';
    }

    protected function itemClass(): string
    {
        return QuoteItem::class;
    }

    protected function pdfProvider(): string
    {
        return QuotePdfDataProvider::class;
    }

    public function getFormattedQuoteDateAttribute($value): mixed
    {
        return $this->formattedDocumentDate;
    }

    public function getQuotePdfUrlAttribute(): string
    {
        return $this->documentPdfUrl;
    }
}
