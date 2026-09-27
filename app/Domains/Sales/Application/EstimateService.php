<?php

namespace App\Domains\Sales\Application;

use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Sales\Contracts\DocumentExchangeRateRecorder;
use App\Domains\Sales\Contracts\EstimateEmailSender;
use App\Domains\Sales\Contracts\EstimatePdfDataProvider;
use App\Domains\Sales\Models\Estimate;
use App\Platform\Mail\Contracts\MailConfigurator;

class EstimateService extends SalesProposalService implements EstimatePdfDataProvider
{
    public function __construct(DocumentItemService $documentItemService, MailConfigurator $mailConfigurator, CustomFieldValueWriter $customFieldValueWriter, DocumentExchangeRateRecorder $exchangeRateRecorder, private readonly EstimateEmailSender $estimateEmailSender)
    {
        parent::__construct($documentItemService, $mailConfigurator, $customFieldValueWriter, $exchangeRateRecorder);
    }

    protected function modelClass(): string
    {
        return Estimate::class;
    }

    protected function sendEmail(array $data): void
    {
        $this->estimateEmailSender->send($data);
    }

    public function sendEstimateData(Estimate $document, array $data): array
    {
        return $this->sendData($document, $data);
    }
}
