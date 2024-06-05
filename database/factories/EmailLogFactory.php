<?php

namespace Database\Factories;

use App\Models\EmailLog;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

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
     */
    public function definition(): array
    {
        return [
            'from' => $this->faker->unique()->safeEmail(),
            'to' => $this->faker->unique()->safeEmail(),
            'subject' => $this->faker->sentence(),
            'body' => $this->faker->text(),
            'mailable_type' => $this->faker->randomElement([Invoice::class, Estimate::class, Payment::class]),
            'mailable_id' => function (array $log) {
                return $log['mailable_type']::factory();
            },
        ];
    }
}
