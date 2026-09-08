<?php

namespace Database\Factories;

use App\Models\ProviderOAuthCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderOAuthCredential>
 */
class ProviderOAuthCredentialFactory extends Factory
{
    protected $model = ProviderOAuthCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'github',
            'client_id' => 'factory-client-id',
            'client_secret' => 'factory-client-secret',
            'redirect_uri' => 'https://example.test/auth/github/callback',
            'scope' => 'read:user user:email',
            'enabled' => true,
        ];
    }
}
