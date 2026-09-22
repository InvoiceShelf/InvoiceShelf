<?php

namespace App\Domains\Metadata\Http\Resources\CustomerPortal;

use App\Platform\Persistence\ModelIdentityMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One recorded answer to a custom field, as the customer portal publishes it.
 *
 * The owner is reported as an id plus a discriminator translated out of the
 * stable database identity into the long class name the v1 API has always
 * published. Every typed answer column travels, and `default_answer` is
 * whichever of them the field's type maps to, read back through that mapping.
 *
 * One key from the administrative payload is absent here: the human-rendered
 * form of the answer. The portal receives the raw stored value and formats it
 * itself, which also means it is spared the administrative payload's habit of
 * failing outright on a dated answer whose company has no date format on file.
 *
 * The definition and the company are each gated on an existence probe against
 * the database, so both are correct whether or not anything was eager loaded
 * and cost a query apiece per serialised row, plus a second to fetch the
 * record once the probe says there is one. The nested definition is the
 * portal's own view of the field; the nested company is the portal's narrower
 * view of the business. This is the payload's established shape and is kept
 * deliberately.
 */
class CustomFieldValueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $value = $this->resource;

        return [
            'id' => $value->id,
            'custom_field_valuable_type' => ModelIdentityMap::publicType($value->custom_field_valuable_type),
            'custom_field_valuable_id' => $value->custom_field_valuable_id,
            'type' => $value->type,
            'boolean_answer' => $value->boolean_answer,
            'date_answer' => $value->date_answer,
            'time_answer' => $value->time_answer,
            'string_answer' => $value->string_answer,
            'number_answer' => $value->number_answer,
            'date_time_answer' => $value->date_time_answer,
            'custom_field_id' => $value->custom_field_id,
            'company_id' => $value->company_id,
            'default_answer' => $value->defaultAnswer,
            'custom_field' => $this->when(
                $value->customField()->exists(),
                fn () => new CustomFieldResource($value->customField)
            ),
        ];
    }
}
