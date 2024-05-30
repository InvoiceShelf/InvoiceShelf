<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Item;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Item::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->text(),
            'company_id' => User::find(1)->companies()->first()->id,
            'price' => $this->faker->randomDigitNotNull(),
            'unit_id' => Unit::factory(),
            'creator_id' => User::where('role', 'super admin')->first()->company_id,
            'currency_id' => Currency::find(1)->id,
            'tax_per_item' => $this->faker->randomElement([true, false]),
        ];
    }
}
