<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ExpenseCategory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'company_id' => User::find(1)->companies()->first()->id,
            'description' => $this->faker->text(),
        ];
    }
}
