<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use InvoiceShelf\Models\FileDisk;

class FileDiskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FileDisk::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'driver' => 'local',
            'set_as_default' => false,
            'credentials' => [
                'driver' => 'local',
                'root' => storage_path('app'),
            ],

        ];
    }
}
