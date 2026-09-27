<?php

namespace App\Domains\Sales\Http\Requests;

class EstimatesRequest extends SalesProposalsRequest
{
    protected function documentKind(): string
    {
        return 'estimate';
    }

    public function getEstimatePayload(): array
    {
        return $this->getProposalPayload();
    }
}
