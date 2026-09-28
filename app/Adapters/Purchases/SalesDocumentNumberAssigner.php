<?php

namespace App\Adapters\Purchases;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Purchases\Contracts\DocumentNumberAssigner;
use App\Domains\Sales\Application\SerialNumberService;

/**
 * Renders purchasing numbers with the serial number service that numbers
 * invoices, reading the format from the company setting the model names and
 * falling back to its default.
 */
class SalesDocumentNumberAssigner implements DocumentNumberAssigner
{
    public function next(string $model, int $companyId): array
    {
        $format = CompanySetting::getSetting($model::NUMBER_FORMAT_SETTING, $companyId) ?: $model::DEFAULT_NUMBER_FORMAT;

        $serial = (new SerialNumberService)
            ->setModel($model)
            ->setCompany($companyId)
            ->withoutCustomerSequence();

        return [
            'number' => $serial->getNextNumber($format),
            'sequence_number' => (int) $serial->nextSequenceNumber,
        ];
    }
}
