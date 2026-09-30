<?php

namespace Database\Factories;

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Models\RecurringInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringInvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RecurringInvoice::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'starts_at' => $this->faker->iso8601(),
            'send_automatically' => false,
            'status' => $this->faker->randomElement(['ON_HOLD', 'ACTIVE']),
            'tax_per_item' => 'NO',
            'tax_included' => false,
            'discount_per_item' => 'NO',
            'sub_total' => $this->faker->randomDigitNotNull(),
            'total' => $this->faker->randomDigitNotNull(),
            'tax' => $this->faker->randomDigitNotNull(),
            'due_amount' => $this->faker->randomDigitNotNull(),
            'discount' => $this->faker->randomDigitNotNull(),
            'discount_val' => $this->faker->randomDigitNotNull(),
            'customer_id' => Customer::factory(),
            'company_id' => User::find(1)->companies()->first()->id,
            'frequency' => '* * 18 * *',
            'limit_by' => $this->faker->randomElement(['NONE', 'COUNT', 'DATE']),
            'limit_count' => $this->faker->numberBetween(1, 9),
            'limit_date' => $this->faker->dateTimeBetween('+1 year', '+5 years')->format('Y-m-d'),
            'exchange_rate' => $this->faker->randomDigitNotNull(),
        ];
    }
}
