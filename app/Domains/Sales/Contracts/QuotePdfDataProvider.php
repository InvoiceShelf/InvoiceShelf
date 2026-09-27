<?php

namespace App\Domains\Sales\Contracts;

use App\Domains\Sales\Models\Quote;

interface QuotePdfDataProvider
{
    public function getPdfData(Quote $quote): mixed;
}
