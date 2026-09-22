<?php

namespace Database\Factories;

use App\Domains\Accounts\Models\User;
use App\Domains\Metadata\Models\CustomField;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomFieldFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CustomField::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'label' => $this->faker->name(),
            'order' => $this->faker->randomDigitNotNull(),
            'is_required' => $this->faker->randomElement([true, false]),
            'model_type' => $this->faker->randomElement(['Customer', 'Invoice', 'Estimate', 'Expense', 'Payment']),
            'company_id' => User::find(1)->companies()->first()->id,
            'slug' => function (array $item) {
                return clean_slug($item['model_type'], $item['label'], $item['company_id']);
            },
            'type' => $this->faker->randomElement(['Text', 'Textarea', 'Phone', 'URL', 'Number', 'Dropdown', 'Switch', 'Date', 'DateTime', 'Time']),
        ];
    }
}
