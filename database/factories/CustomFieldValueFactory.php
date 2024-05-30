<?php

namespace Database\Factories;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomFieldValueFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CustomFieldValue::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'custom_field_valuable_type' => $this->faker->name(),
            'custom_field_valuable_id' => 1,
            'type' => $this->faker->name(),
            'custom_field_id' => CustomField::factory(),
            'company_id' => User::find(1)->companies()->first()->id,
        ];
    }
}
