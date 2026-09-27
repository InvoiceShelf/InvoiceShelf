<?php

namespace App\Domains\Sales\Models;

use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Contracts\EstimatePdfDataProvider;

class Estimate extends SalesProposal
{
    protected $table = 'estimates';

    protected $appends = ['formattedExpiryDate', 'formattedEstimateDate', 'estimatePdfUrl'];

    public function documentKind(): string
    {
        return 'estimate';
    }

    protected function itemClass(): string
    {
        return EstimateItem::class;
    }

    protected function pdfProvider(): string
    {
        return EstimatePdfDataProvider::class;
    }

    public function getFormattedEstimateDateAttribute($value): mixed
    {
        return $this->formattedDocumentDate;
    }

    public function getEstimatePdfUrlAttribute(): string
    {
        return $this->documentPdfUrl;
    }

    public function scopeEstimatesBetween($query, $start, $end)
    {
        return $this->scopeDocumentsBetween($query, $start, $end);
    }

    public function scopeWhereEstimateNumber($query, $number)
    {
        return $this->scopeWhereDocumentNumber($query, $number);
    }

    public function scopeWhereEstimate($query, $id)
    {
        return $this->scopeWhereDocument($query, $id);
    }

    public function checkForEstimateConvertAction(): bool
    {
        return app(EstimateService::class)->applyConversionAction($this);
    }
}
