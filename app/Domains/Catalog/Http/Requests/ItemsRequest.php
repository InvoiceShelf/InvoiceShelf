<?php

namespace App\Domains\Catalog\Http\Requests;

use App\Domains\Metadata\Http\Requests\Concerns\ValidatesCustomFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Incoming payload for creating or editing a catalog item.
 *
 * Only what describes the item is taken from the client. The owning company,
 * the creator and the currency are stamped from the request context further
 * down, so they are absent from the rules and never survive validation even
 * when a client sends them. Item taxes ride along outside these rules and are
 * read straight off the request by the controller.
 */
class ItemsRequest extends FormRequest
{
    use ValidatesCustomFields;

    /**
     * Access is settled by the item policy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Presence checks only: a name and a price are demanded, the unit and the
     * description may be left out or sent empty. Nothing is type-checked here,
     * so the price is whatever the column makes of the submitted value.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'price' => ['required'],
            'unit_id' => ['nullable'],
            'description' => ['nullable'],
            ...$this->customFieldRules(),
        ];
    }

    /**
     * The columns written to the catalogue item.
     *
     * The answers arrive alongside them and are persisted separately, so they
     * are taken out here rather than left for the model to ignore.
     *
     * @return array<string, mixed>
     */
    public function getItemPayload(): array
    {
        return $this->withoutCustomFields($this->validated());
    }
}
