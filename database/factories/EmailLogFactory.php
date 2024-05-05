<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use InvoiceShelf\Models\EmailLog;
use InvoiceShelf\Models\Estimate;
use InvoiceShelf\Models\Invoice;
use InvoiceShelf\Models\Payment;

class EmailLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EmailLog::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'from' => $this->faker->unique()->safeEmail,
            'to' => $this->faker->unique()->safeEmail,
            'subject' => $this->faker->sentence,
            'body' => $this->faker->text,
            'mailable_type' => $this->faker->randomElement([Invoice::class, Estimate::class, Payment::class]),
            'mailable_id' => function (array $log) {
                return $log['mailable_type']::factory();
            },
        ];
    }
}
