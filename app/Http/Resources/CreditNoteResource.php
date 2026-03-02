<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'credit_note_number' => $this->credit_note_number,
            'credit_note_date' => $this->credit_note_date,
            'notes' => $this->getNotes(),
            'amount' => $this->amount,
            'unique_hash' => $this->unique_hash,
            'invoice_id' => $this->invoice_id,
            'company_id' => $this->company_id,
            'creator_id' => $this->creator_id,
            'customer_id' => $this->customer_id,
            'exchange_rate' => $this->exchange_rate,
            'base_amount' => $this->base_amount,
            'currency_id' => $this->currency_id,
            'transaction_id' => $this->transaction_id,
            'sequence_number' => $this->sequence_number,
            'formatted_created_at' => $this->formattedCreatedAt,
            'formatted_credit_note_date' => $this->formattedCreditNoteDate,
            'credit_note_pdf_url' => $this->paymentPdfUrl,
            'customer' => $this->when($this->customer()->exists(), function () {
                return new CustomerResource($this->customer);
            }),
            'invoice' => $this->when($this->invoice()->exists(), function () {
                return new InvoiceResource($this->invoice);
            }),
            'fields' => $this->when($this->fields()->exists(), function () {
                return CustomFieldValueResource::collection($this->fields);
            }),
            'company' => $this->when($this->company()->exists(), function () {
                return new CompanyResource($this->company);
            }),
            'currency' => $this->when($this->currency()->exists(), function () {
                return new CurrencyResource($this->currency);
            }),
            'transaction' => $this->when($this->transaction()->exists(), function () {
                return new TransactionResource($this->transaction);
            }),
        ];
    }
}
