<?php

namespace Database\Factories;

use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EstimateItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EstimateItem::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'name' => function (array $item) {
                return Item::find($item['item_id'])->name;
            },
            'description' => function (array $item) {
                return Item::find($item['item_id'])->description;
            },
            'price' => function (array $item) {
                return Item::find($item['item_id'])->price;
            },
            'estimate_id' => Estimate::factory(),
            'quantity' => $this->faker->randomDigitNotNull(),
            'company_id' => User::find(1)->companies()->first()->id,
            'tax' => $this->faker->randomDigitNotNull(),
            'total' => function (array $item) {
                return $item['price'] * $item['quantity'];
            },
            'discount_type' => $this->faker->randomElement(['percentage', 'fixed']),
            'discount_val' => function (array $estimate) {
                return $estimate['discount_type'] == 'percentage' ? $this->faker->numberBetween($min = 0, $max = 100) : $this->faker->randomDigitNotNull();
            },
            'discount' => function (array $estimate) {
                return $estimate['discount_type'] == 'percentage' ? (($estimate['discount_val'] * $estimate['total']) / 100) : $estimate['discount_val'];
            },
            'exchange_rate' => $this->faker->randomDigitNotNull(),
            'base_discount_val' => $this->faker->randomDigitNotNull(),
            'base_price' => $this->faker->randomDigitNotNull(),
            'base_total' => $this->faker->randomDigitNotNull(),
            'base_tax' => $this->faker->randomDigitNotNull(),
        ];
    }
}
