<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'unique_hash' => str_random(20),
            'name' => $this->faker->name(),
            'owner_id' => User::where('role', 'super admin')->first()->id,
            'slug' => $this->faker->word(),
        ];
    }
}
