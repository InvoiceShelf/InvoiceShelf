<?php

namespace InvoiceShelf\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TaxCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
