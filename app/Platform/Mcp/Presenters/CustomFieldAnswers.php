<?php

namespace App\Platform\Mcp\Presenters;

use Illuminate\Database\Eloquent\Model;

/**
 * A record's custom field answers, by the field's slug and label.
 */
final class CustomFieldAnswers
{
    /**
     * @return list<array{slug: string|null, label: string|null, value: mixed}>
     */
    public static function of(Model $record): array
    {
        $record->loadMissing('fields.customField');

        return $record->fields
            ->map(fn ($answer) => [
                'slug' => $answer->customField?->slug,
                'label' => $answer->customField?->label,
                'value' => $answer->defaultAnswer,
            ])
            ->values()
            ->all();
    }
}
