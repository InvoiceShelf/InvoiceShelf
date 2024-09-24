<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Tax;
use App\Models\TaxType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Tax::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tax_type_id' => TaxType::factory(),
            'percent' => function (array $item) {
                return TaxType::find($item['tax_type_id'])->percent;
            },
            'name' => function (array $item) {
                return TaxType::find($item['tax_type_id'])->name;
            },
            'company_id' => User::find(1)->companies()->first()->id,
            'amount' => $this->faker->randomDigitNotNull(),
            'compound_tax' => $this->faker->randomDigitNotNull(),
            'base_amount' => $this->faker->randomDigitNotNull(),
            'currency_id' => Currency::where('name', 'US Dollar')->first()->id,
        ];
    }
}
