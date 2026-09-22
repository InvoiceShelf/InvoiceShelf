<?php

namespace App\Domains\Contacts\Http\Resources\CustomerPortal;

use App\Domains\Accounts\Http\Resources\CustomerPortal\CompanyResource;
use App\Domains\Metadata\Http\Resources\CustomerPortal\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CustomerPortal\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in contact as the customer portal publishes it.
 *
 * A narrower view than the admin one: no password flag and no account summary
 * figures, only the profile fields the portal itself renders, together with the
 * addresses, custom field values, company and currency when those exist.
 */
class CustomerResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'contact_name' => $this->contact_name,
            'company_name' => $this->company_name,
            'website' => $this->website,
            'enable_portal' => $this->enable_portal,
            'currency_id' => $this->currency_id,
            'company_id' => $this->company_id,
            'facebook_id' => $this->facebook_id,
            'google_id' => $this->google_id,
            'github_id' => $this->github_id,
            'formatted_created_at' => $this->formattedCreatedAt,
            'avatar' => $this->avatar,
            'prefix' => $this->prefix,
            'tax_id' => $this->tax_id,
            'billing' => $this->when(
                $this->billingAddress()->exists(),
                fn () => new AddressResource($this->billingAddress)
            ),
            'shipping' => $this->when(
                $this->shippingAddress()->exists(),
                fn () => new AddressResource($this->shippingAddress)
            ),
            'fields' => $this->when(
                $this->printedFields()->exists(),
                fn () => CustomFieldValueResource::collection($this->printedFields)
            ),
            'company' => $this->when(
                $this->company()->exists(),
                fn () => new CompanyResource($this->company)
            ),
            'currency' => $this->when(
                $this->currency()->exists(),
                fn () => new CurrencyResource($this->currency)
            ),
        ];
    }
}
