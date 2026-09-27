<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimateItem extends SalesProposalItem
{
    protected $table = 'estimate_items';

    protected function documentKind(): string
    {
        return 'estimate';
    }

    protected function documentClass(): string
    {
        return Estimate::class;
    }

    public function estimate(): BelongsTo
    {
        return $this->proposal();
    }
}
