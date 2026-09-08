<?php

namespace Database\Factories;

use App\Models\ProviderToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderToken>
 */
class ProviderTokenFactory extends Factory
{
    protected $model = ProviderToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'github',
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'token_type' => 'bearer',
            'scope' => 'read:user user:email',
            'provider_user' => $this->faker->userName(),
            'expires_at' => now()->addHour(),
        ];
    }
}
