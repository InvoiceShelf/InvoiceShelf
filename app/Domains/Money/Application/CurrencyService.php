<?php

namespace App\Domains\Money\Application;

use App\Domains\Money\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

class CurrencyService
{
    public function __construct(
        private readonly CurrencyCatalog $catalog,
    ) {}

    /**
     * Retrieve all currencies with commonly-used currencies sorted first.
     *
     * @return Collection<int, Currency>
     */
    public function getAllWithCommonFirst(): Collection
    {
        $currencies = Currency::query()->get();

        $commonCodes = Currency::COMMON_CURRENCY_CODES;

        $common = $currencies->filter(fn (Currency $c): bool => in_array($c->code, $commonCodes, true))
            ->sortBy(fn (Currency $c): int => array_search($c->code, $commonCodes));

        // Case-insensitively, or a name whose second letter is capitalised
        // ("CFP Franc") sorts by byte value and lands at the top of its
        // letter instead of beside its neighbours.
        $rest = $currencies->reject(fn (Currency $c): bool => in_array($c->code, $commonCodes, true))
            ->sortBy(fn (Currency $c): string => mb_strtolower($c->name));

        return $common->concat($rest)->values();
    }

    /**
     * Bring the currencies table in line with {@see CurrencyCatalog}.
     *
     * Insert a code that is absent, refresh the descriptive columns of one
     * that is present, and never delete. A code the catalogue does not list --
     * one an older release or a module put there -- is left exactly as it is,
     * because records point at it.
     *
     * Nothing here can overwrite something a person typed: currencies are
     * reference data with no CRUD surface, so the catalogue is the only author
     * these rows have ever had.
     *
     * Idempotent by construction, which is what lets the seeder run a second
     * time without duplicating a row and lets the admin area offer this as a
     * button rather than the project shipping a migration per currency.
     *
     * @return array{added: list<string>, updated: list<string>}
     */
    public function sync(): array
    {
        $existing = Currency::query()->get()->keyBy('code');

        $added = [];
        $updated = [];

        foreach ($this->catalog->all() as $entry) {
            $currency = $existing->get($entry['code']);

            if ($currency === null) {
                Currency::create($entry);
                $added[] = $entry['code'];

                continue;
            }

            $currency->fill($entry);

            if ($currency->isDirty()) {
                $currency->save();
                $updated[] = $entry['code'];
            }
        }

        return ['added' => $added, 'updated' => $updated];
    }
}
