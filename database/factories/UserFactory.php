<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'company_name' => $this->faker->company(),
            'contact_name' => $this->faker->name(),
            'website' => $this->faker->url(),
            'enable_portal' => true,
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'role' => 'super admin',
            'password' => Hash::make('secret'),
            'currency_id' => Currency::first()->id,
        ];
    }
}
