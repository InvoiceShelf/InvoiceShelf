<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use InvoiceShelf\Models\Note;
use InvoiceShelf\Models\User;

class NoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Note::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'type' => $this->faker->randomElement(['Invoice', 'Estimate', 'Payment']),
            'name' => $this->faker->word,
            'notes' => $this->faker->text,
            'company_id' => User::find(1)->companies()->first()->id,
        ];
    }
}
