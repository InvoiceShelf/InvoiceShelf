<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'format' => $this->format,
            'status' => $this->status,
            'xml_path' => $this->xml_path,
            'pdf_path' => $this->pdf_path,
            'generated_at' => $this->generated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
