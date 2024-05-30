<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'company_name' => $this->faker->company(),
            'contact_name' => $this->faker->name(),
            'prefix' => $this->faker->randomDigitNotNull(),
            'website' => $this->faker->url(),
            'enable_portal' => true,
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'company_id' => User::find(1)->companies()->first()->id,
            'password' => Hash::make('secret'),
            'currency_id' => Currency::find(1)->id,
        ];
    }
}
