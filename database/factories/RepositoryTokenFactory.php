<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\RepositoryToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepositoryToken>
 */
class RepositoryTokenFactory extends Factory
{
    protected $model = RepositoryToken::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'provider' => 'github',
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'token_type' => 'bearer',
            'scope' => 'repo read:user',
            'provider_user' => $this->faker->userName(),
            'expires_at' => now()->addDays(30),
        ];
    }
}
