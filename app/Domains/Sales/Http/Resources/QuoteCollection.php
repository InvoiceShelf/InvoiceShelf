<?php

namespace App\Domains\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of quotes for the admin API.
 *
 * Named after its member resource, so rows are published through
 * QuoteResource and the pagination envelope comes from the framework.
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
