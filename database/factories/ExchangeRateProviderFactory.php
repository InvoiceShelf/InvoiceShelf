<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ExchangeRateProvider;

class ExchangeRateProviderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ExchangeRateProvider::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'driver' => $this->faker->word(),
            'key' => str_random(10),
            'active' => $this->faker->randomElement([true, false]),
        ];
    }
}
