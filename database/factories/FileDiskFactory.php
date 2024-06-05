<?php

namespace Database\Factories;

use App\Models\FileDisk;
use Illuminate\Database\Eloquent\Factories\Factory;

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
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'driver' => 'local',
            'set_as_default' => false,
            'credentials' => [
                'driver' => 'local',
                'root' => storage_path('app'),
            ],

        ];
    }
}
