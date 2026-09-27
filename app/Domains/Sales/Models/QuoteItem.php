<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends SalesProposalItem
{
    protected $table = 'quote_items';

    protected function documentKind(): string
    {
        return 'quote';
    }

    protected function documentClass(): string
    {
        return Quote::class;
    }

    public function quote(): BelongsTo
    {
        return $this->proposal();
    }
}
