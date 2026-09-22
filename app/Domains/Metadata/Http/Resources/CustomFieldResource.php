<?php

namespace App\Domains\Metadata\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A custom-field definition as the admin API publishes it.
 *
 * The definition describes an extra input an operator fills in: what it is
 * called, which record family it hangs off, where it sits in the form, which
 * widget renders it, whether an answer is mandatory, and the choices a
 * dropdown offers. Its slug is the handle the document-formatting engine
 * matches on -- minted once at creation and never reissued, so it outlives a
 * rename and existing templates keep resolving.
 *
 * Every typed answer column is published side by side, and only one of them
 * is ever populated: the one the declared input type maps to. `default_answer`
 * is that column read back through the mapping, so a consumer wanting the
 * prefilled answer takes the single derived key instead of guessing which
 * column to look in.
 *
 * Two keys are worked out at read time rather than stored. `in_use` asks the
 * database whether any answer has ever been recorded against the definition,
 * and the owning company is published only when its relation resolves, which
 * is a second probe followed by a third read to fetch the row. All of that
 * runs per serialised row, so a page of definitions pays for it once per row.
 * That is the payload's established shape and is kept deliberately.
 *
 * Nothing here is gated on the caller: `in_use` is reported but carries no
 * weight, and the delete path ignores it entirely.
 */
class CustomFieldResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $field = $this->resource;

        return [
            'id' => $field->id,
            'name' => $field->name,
            'slug' => $field->slug,
            'label' => $field->label,
            'model_type' => $field->model_type,
            'placement' => $field->placement,
            'validation' => $field->validation,
            'type' => $field->type,
            'placeholder' => $field->placeholder,
            'options' => $field->options,
            'boolean_answer' => $field->boolean_answer,
            'date_answer' => $field->date_answer,
            'time_answer' => $field->time_answer,
            'string_answer' => $field->string_answer,
            'number_answer' => $field->number_answer,
            'date_time_answer' => $field->date_time_answer,
            'is_required' => $field->is_required,
            'in_use' => $field->in_use,
            'order' => $field->order,
            'company_id' => $field->company_id,
            'default_answer' => $field->default_answer,
        ];
    }
}
