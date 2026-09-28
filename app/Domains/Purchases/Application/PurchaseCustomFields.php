<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Metadata\Application\CustomFieldAnswerValidator;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Resolves defaults and partial updates before any purchase or schedule is written. */
class PurchaseCustomFields
{
    public function __construct(private readonly CustomFieldValueWriter $writer, private readonly CustomFieldAnswerValidator $constraints) {}

    public static function answerRules(int $companyId, string $model, string $key = 'customFields'): array
    {
        return [
            $key => ['sometimes', 'array', 'list'],
            "{$key}.*" => ['required', 'array'],
            "{$key}.*.id" => ['required', 'integer', 'distinct', Rule::exists('custom_fields', 'id')->where('company_id', $companyId)->where('model_type', $model)],
            "{$key}.*.value" => ['present'],
        ];
    }

    public function definitions(int $companyId, string $model): Collection
    {
        return CustomField::query()->where('company_id', $companyId)->where('model_type', $model)->orderBy('order')->orderBy('id')->get();
    }

    public function saved(Model $record): array
    {
        return $record->exists ? $record->fields()->get()->map(fn ($field) => ['id' => $field->custom_field_id, 'value' => $field->defaultAnswer])->all() : [];
    }

    /** Only persisted templates may drop references to definitions removed since they were saved. */
    public function retained(int $companyId, string $model, array $answers): array
    {
        $ids = $this->definitions($companyId, $model)->modelKeys();

        return array_values(array_filter($answers, fn ($answer) => in_array($answer['id'], $ids)));
    }

    public function resolve(int $companyId, string $model, array $submitted, array $existing = [], string $key = 'customFields'): array
    {
        $payload = [];
        Arr::set($payload, $key, $submitted);
        Validator::make($payload, self::answerRules($companyId, $model, $key))->validate();
        $values = collect($existing)->keyBy('id');
        $indices = [];
        foreach ($submitted as $index => $answer) {
            $values->put($answer['id'], $answer);
            $indices[$answer['id']] = $index;
        }
        $answers = [];
        $validator = Validator::make([], []);
        foreach ($this->definitions($companyId, $model) as $field) {
            $value = $values->has($field->id) ? $values->get($field->id)['value'] : $field->defaultAnswer;
            $attribute = $key.'.'.($indices[$field->id] ?? count($submitted) + count($answers)).'.value';
            $typeRules = match ($field->type) {
                'Number' => ['numeric'],
                'Switch' => ['boolean'],
                'Date' => ['date_format:Y-m-d'],
                'Time' => ['date_format:H:i,H:i:s'],
                'DateTime' => ['date_format:Y-m-d H:i,Y-m-d H:i:s'],
                default => ['string'],
            };
            $typed = Validator::make(['value' => $value], ['value' => ['nullable', ...$typeRules]], [], ['value' => $field->label]);
            if ($typed->fails()) {
                $validator->errors()->add($attribute, $typed->errors()->first('value'));
            } else {
                $this->constraints->validate($validator, $attribute, $value, $field, $companyId);
            }
            $answers[] = ['id' => $field->id, 'value' => $value === '' ? null : $value];
        }
        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }

        return $answers;
    }

    public function save(Model $record, array $answers): void
    {
        $this->writer->update($record, $answers);
    }
}
