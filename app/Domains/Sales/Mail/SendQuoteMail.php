<?php

namespace App\Domains\Sales\Mail;

use App\Domains\Sales\Models\Quote;

class SendQuoteMail extends SendEstimateMail
{
    protected string $kind = 'quote';

    protected string $modelClass = Quote::class;
}
