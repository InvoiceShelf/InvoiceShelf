<?php

namespace App\Domains\Sales\Http\Resources\CustomerPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of quotes for the customer portal.
 *
 * Namespace and class name together select the portal QuoteResource as the
 * member resource; the pagination envelope comes from the framework.
 */
class QuoteCollection extends ResourceCollection
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
