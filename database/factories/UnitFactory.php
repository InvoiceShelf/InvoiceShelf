<?php

namespace Database\Factories;

use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'unit_code' => Unit::DEFAULT_UNIT_CODE,
            'company_id' => User::find(1)->companies()->first()->id,
        ];
    }
}
