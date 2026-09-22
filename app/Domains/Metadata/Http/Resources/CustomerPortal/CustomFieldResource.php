<?php

namespace App\Domains\Metadata\Http\Resources\CustomerPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A custom-field definition as the customer portal publishes it.
 *
 * Key for key this is the administrative payload: the naming, the record
 * family the field hangs off, the widget, the ordering, whether an answer is
 * mandatory, the dropdown choices, the frozen slug the formatting engine
 * matches on, every typed answer column, and the prefilled answer resolved out
 * of whichever column the type maps to. What differs is the company nested
 * underneath, which is the portal's narrower view of the business rather than
 * the administrative one.
 *
 * The read-time costs come across unchanged too: `in_use` asks the database
 * whether any answer exists against the definition, and the nested company
 * costs a probe plus a read. Both run per serialised row. The shape is the
 * established contract and is kept as it is -- including the fact that a
 * portal consumer is told how the field is configured internally, `in_use`
 * and the administrative defaults included.
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
