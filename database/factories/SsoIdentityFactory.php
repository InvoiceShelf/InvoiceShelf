<?php

namespace Database\Factories;

use App\Domains\Accounts\Models\SsoIdentity;
use App\Domains\Accounts\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SsoIdentityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SsoIdentity::class;

    /**
     * Define the model's default state: an OpenID Connect account.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => SsoIdentity::TYPE_OIDC,
            'issuer' => 'https://sso.example.com/realms/example',
            'subject' => $this->faker->unique()->uuid(),
            'last_used_at' => null,
        ];
    }
}
