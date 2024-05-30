<?php

namespace Database\Factories;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanySettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CompanySetting::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'option' => $this->faker->word(),
            'value' => $this->faker->word(),
            'company_id' => User::find(1)->companies()->first()->id,
        ];
    }
}
