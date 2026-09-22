<?php

namespace App\Domains\Metadata\Concerns;

use App\Domains\Metadata\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a record the custom-field answers held against it.
 *
 * Mixed into every type that can carry them -- customers, documents, the
 * lines on a document, payments, expenses, users. Answers are reachable
 * either as a relation or one at a time by the slug a template names them
 * with, and they are cleared when the record they hang off is deleted.
 *
 * That clean-up is registered from `booted()` rather than from the per-trait
 * boot method the framework calls, which means a model declaring its own
 * `booted()` replaces this one and quietly keeps its answers after deletion.
 * Payments are in exactly that position today.
 */
trait HasCustomFields
{
    /**
     * The answers recorded against this record.
     */
    public function fields(): MorphMany
    {
        return $this->morphMany(
            CustomFieldValue::class,
            'custom_field_valuable',
        );
    }

    /**
     * Drop the answers when the record that owns them goes.
     *
     * The existence check spares the delete statement when there is nothing
     * to remove, at the price of a query that asks first.
     */
    protected static function booted()
    {
        static::deleting(function ($record) {
            if ($record->fields()->exists()) {
                $record->fields()->delete();
            }
        });
    }

    /**
     * The answer this record holds for the field with the given slug, with
     * the definition already loaded alongside it.
     *
     * Null when this record never answered that field -- and equally when no
     * field carries the slug at all.
     */
    public function getCustomFieldBySlug($slug)
    {
        return $this->fields()
            ->with('customField')
            ->whereHas('customField', fn ($definition) => $definition->where('slug', $slug))
            ->first();
    }

    /**
     * The answer itself, read from the column its type maps to. This is what
     * a document placeholder naming the slug resolves to.
     */
    public function getCustomFieldValueBySlug($slug)
    {
        return $this->getCustomFieldBySlug($slug)?->defaultAnswer;
    }

    /**
     * The same answer, formatted for a reader: a date in the company's
     * configured format, so a custom date printed on a document matches the
     * dates already on it. This is what the PDF templates use.
     */
    public function getFormattedCustomFieldValueBySlug($slug)
    {
        return $this->getCustomFieldBySlug($slug)?->formatted_answer;
    }
}
