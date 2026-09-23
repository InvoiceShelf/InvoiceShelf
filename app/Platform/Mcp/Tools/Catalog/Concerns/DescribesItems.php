<?php

namespace App\Platform\Mcp\Tools\Catalog\Concerns;

use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Taxation\Models\TaxType;
use App\Support\DocumentTaxes;
use App\Support\MinorUnits;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;

/**
 * A catalogue item's arguments, and the item form's submission built from
 * them on top of what is stored.
 */
trait DescribesItems
{
    /**
     * @return array<string, mixed>
     */
    protected function itemSchema(JsonSchema $schema, bool $creating): array
    {
        $name = $schema->string();
        $price = $schema->string()->description('Price of one unit in the company currency, a decimal in major units like "95.00".');

        return [
            'name' => $creating ? $name->required() : $name,
            'price' => $creating ? $price->required() : $price,
            'description' => $schema->string(),
            'unit' => $schema->string()->description('A unit name from get_company_context, like "hours".'),
            'tax_type_ids' => $schema->array()->items($schema->integer())->description('Sales taxes that lines of this item start with, when the company taxes each line. Replaces the current ones.'),
            'custom_fields' => $schema->array()->items($schema->object([
                'slug' => $schema->string(),
                'value' => $schema->string(),
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $given
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>|null}
     *
     * @throws ValidationException
     */
    protected function itemInput(array $given, int $companyId, ?Item $existing = null): array
    {
        $input = [
            'name' => $given['name'] ?? $existing?->name,
            'description' => array_key_exists('description', $given) ? $given['description'] : $existing?->description,
            'unit_id' => $existing?->unit_id,
            'price' => $existing?->price,
        ];

        if (array_key_exists('price', $given)) {
            $input['price'] = MinorUnits::fromMajor($given['price']);

            if ($input['price'] === null) {
                throw ValidationException::withMessages(['price' => 'The price is an amount in major units with at most two decimals, like "95.00".']);
            }
        }

        if (array_key_exists('unit', $given)) {
            $input['unit_id'] = $given['unit'] === null || $given['unit'] === '' ? null : Unit::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $given['unit']))])
                ->value('id');

            if ($given['unit'] && ! $input['unit_id']) {
                $units = Unit::query()->where('company_id', $companyId)->orderBy('name')->pluck('name')->implode(', ');

                throw ValidationException::withMessages(['unit' => "The unit is one of: {$units}."]);
            }
        }

        if (array_key_exists('custom_fields', $given)) {
            $input['customFields'] = array_map(
                fn ($answer) => is_array($answer) ? array_intersect_key($answer, array_flip(['slug', 'id', 'value'])) : $answer,
                (array) $given['custom_fields'],
            );
        }

        $taxes = array_key_exists('tax_type_ids', $given)
            ? $this->itemTaxes((array) $given['tax_type_ids'], $companyId, (int) $input['price'])
            : null;

        return [$input, $taxes];
    }

    /**
     * The tax rows the item form submits for the chosen types.
     *
     * @param  array<int, mixed>  $ids
     * @return list<array<string, mixed>>
     */
    private function itemTaxes(array $ids, int $companyId, int $price): array
    {
        $types = TaxType::query()
            ->where('company_id', $companyId)
            ->where('type', TaxType::TYPE_GENERAL)
            ->where(fn ($query) => $query->whereNull('transaction_type')->orWhere('transaction_type', TaxType::TRANSACTION_TYPE_SALES))
            ->whereIn('id', array_map('intval', $ids))
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach (array_values($ids) as $position => $id) {
            $type = $types->get((int) $id);

            if (! $type) {
                throw ValidationException::withMessages(["tax_type_ids.{$position}" => "Tax type {$id} is not one of the company's sales taxes."]);
            }

            $rows[] = [
                'tax_type_id' => $type->id,
                'calculation_type' => $type->calculation_type,
                'fixed_amount' => $type->fixed_amount,
                // The item form's arithmetic: the percent of the price in
                // major units, rounded.
                'amount' => $type->calculation_type === 'fixed' ? $type->fixed_amount : DocumentTaxes::round($price / 100 * $type->percent),
                'percent' => $type->percent,
                'name' => $type->name,
                'collective_tax' => 0,
            ];
        }

        return $rows;
    }
}
