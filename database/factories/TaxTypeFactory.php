<?php

namespace Database\Factories;

use App\Models\TaxType;
use App\Models\User;
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
            'company_id' => User::find(1)->companies()->first()->id,
            'percent' => $this->faker->numberBetween($min = 0, $max = 100),
            'fixed_amount' => null,
            'description' => $this->faker->text(),
            'compound_tax' => 0,
            'collective_tax' => 0,
        ];
    }
}
