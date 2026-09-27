<?php

namespace App\Domains\Sales\Http\Resources\CustomerPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of quote lines for the customer portal.
 *
 * Namespace and class name together select the portal QuoteItemResource as
 * the member resource.
 */
class QuoteItemCollection extends ResourceCollection
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
