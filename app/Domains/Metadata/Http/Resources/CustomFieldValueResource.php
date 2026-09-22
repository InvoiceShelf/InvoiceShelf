<?php

namespace App\Domains\Metadata\Http\Resources;

use App\Domains\Accounts\Models\CompanySetting;
use App\Platform\Persistence\ModelIdentityMap;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One recorded answer to a custom field, as the admin API publishes it.
 *
 * A row here belongs to whatever it was filled in against -- a customer, a
 * document, one line of a document, a payment, an expense. The owner is
 * reported as a pair: the record's id, and a discriminator translated out of
 * the stable database identity into the long class name the v1 API has always
 * published, so consumers written against that shape keep working.
 *
 * As on the definition, every typed answer column travels and only the one
 * the field's type maps to holds anything; `default_answer` is that column
 * read back through the mapping. `default_formatted_answer` is the same datum
 * rendered for a human -- see the method below for the three ways that goes.
 *
 * Both associations are gated on an existence probe run against the database,
 * so they are correct whether or not anything was eager loaded, at the price
 * of a query apiece per serialised row plus a second to fetch the record once
 * the probe says there is one. Eager loading does not spare the probe. This is
 * the payload's established shape and is kept deliberately.
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
            'default_formatted_answer' => $this->dateTimeFormat(),
            'custom_field' => $this->when(
                $value->customField()->exists(),
                fn () => new CustomFieldResource($value->customField)
            ),
        ];
    }

    /**
     * The stored answer rendered the way a reader should see it.
     *
     * The field's type decides the treatment. A moment in time is fixed to a
     * minute in an ISO-looking layout that ignores the company's preferences
     * entirely; a plain date goes through the owning company's configured date
     * format; everything else -- text, numbers, switches, times, and any type
     * the mapping does not recognise -- is handed back exactly as stored.
     *
     * An answer that reads as empty short-circuits to null before any of that,
     * which sweeps up more than blanks: a switch turned off and a numeric zero
     * are both falsy, so neither ever reaches this key. Two rough edges are
     * preserved as they are. The column the type maps to is resolved before
     * the emptiness check, so a row carrying no type at all fails here rather
     * than returning null; and a company with no date format on file hands a
     * null format down to the formatter, which rejects it -- a dated answer
     * belonging to such a company cannot be serialised at all.
     */
    public function dateTimeFormat()
    {
        $value = $this->resource;

        $column = getCustomFieldValueKey($value->type);
        $answer = $value->default_answer;

        if (! $answer) {
            return null;
        }

        return match ($column) {
            'date_time_answer' => Carbon::parse($answer)->format('Y-m-d H:i'),
            'date_answer' => Carbon::parse($answer)->format(
                CompanySetting::getSetting('carbon_date_format', $value->company_id)
            ),
            default => $answer,
        };
    }
}
