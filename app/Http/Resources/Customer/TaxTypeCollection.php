<?php

namespace InvoiceShelf\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TaxTypeCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
