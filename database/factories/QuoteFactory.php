<?php

namespace Database\Factories;

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Sales\Application\SerialNumberService;
use App\Domains\Sales\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Quote::class;

    public function sent()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Quote::STATUS_SENT,
            ];
        });
    }

    public function viewed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Quote::STATUS_VIEWED,
            ];
        });
    }

    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Quote::STATUS_EXPIRED,
            ];
        });
    }

    public function accepted()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Quote::STATUS_ACCEPTED,
            ];
        });
    }

    public function rejected()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Quote::STATUS_REJECTED,
            ];
        });
    }

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $sequenceNumber = (new SerialNumberService)
            ->setModel(new Quote)
            ->setCompany(User::find(1)->companies()->first()->id)
            ->setNextNumbers();

        return [
            'quote_date' => $this->faker->date('Y-m-d', 'now'),
            'expiry_date' => $this->faker->date('Y-m-d', 'now'),
            'quote_number' => $sequenceNumber->getNextNumber(),
            'sequence_number' => $sequenceNumber->nextSequenceNumber,
            'customer_sequence_number' => $sequenceNumber->nextCustomerSequenceNumber,
            'reference_number' => $sequenceNumber->getNextNumber(),
            'company_id' => User::find(1)->companies()->first()->id,
            'status' => Quote::STATUS_DRAFT,
            'template_name' => 'quote1',
            'sub_total' => $this->faker->randomDigitNotNull(),
            'total' => $this->faker->randomDigitNotNull(),
            'discount_type' => $this->faker->randomElement(['percentage', 'fixed']),
            'discount_val' => function (array $quote) {
                return $quote['discount_type'] == 'percentage' ? $this->faker->numberBetween($min = 0, $max = 100) : $this->faker->randomDigitNotNull();
            },
            'discount' => function (array $quote) {
                return $quote['discount_type'] == 'percentage' ? (($quote['discount_val'] * $quote['total']) / 100) : $quote['discount_val'];
            },
            'tax_per_item' => 'YES',
            'tax_included' => false,
            'discount_per_item' => 'No',
            'tax' => $this->faker->randomDigitNotNull(),
            'notes' => $this->faker->text(80),
            'unique_hash' => str_random(60),
            'customer_id' => Customer::factory(),
            'exchange_rate' => $this->faker->randomDigitNotNull(),
            'base_discount_val' => $this->faker->randomDigitNotNull(),
            'base_sub_total' => $this->faker->randomDigitNotNull(),
            'base_total' => $this->faker->randomDigitNotNull(),
            'base_tax' => $this->faker->randomDigitNotNull(),
            'currency_id' => Currency::find(1)->id,
        ];
    }
}
