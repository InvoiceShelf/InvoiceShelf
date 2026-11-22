<?php

namespace Database\Factories;

use App\Domains\Accounts\Models\User;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TaxType::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'calculation_type' => 'percentage',
            'transaction_type' => TaxType::TRANSACTION_TYPE_SALES,
            'company_id' => User::find(1)->companies()->first()->id,
            'percent' => $this->faker->numberBetween($min = 0, $max = 100),
            'fixed_amount' => null,
            'description' => $this->faker->text(),
            'compound_tax' => 0,
            'collective_tax' => 0,
            'tax_category_code' => TaxType::TAX_CATEGORY_CODE_STANDARD,
        ];
    }

    /**
     * A tax type exempt from VAT, carrying the exemption reason EN 16931
     * requires (BT-120).
     */
    public function exempt(string $reason = '§ 19 UStG'): static
    {
        return $this->state(fn (): array => [
            'percent' => 0,
            'tax_category_code' => TaxType::TAX_CATEGORY_CODE_EXEMPT,
            'tax_exemption_reason' => $reason,
        ]);
    }
}
