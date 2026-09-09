<?php

use App\Models\ProviderOAuthCredential;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated users can save OAuth credentials for a provider', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('dashboard.provider.oauth-credentials.store'), [
        'provider' => 'github',
        'client_id' => 'github-client-id',
        'client_secret' => 'super-secret-value-123',
        'redirect_uri' => 'https://example.test/auth/github/callback',
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('provider_oauth_credentials', [
        'user_id' => $user->id,
        'provider' => 'github',
        'client_id' => 'github-client-id',
        'client_secret' => 'super-secret-value-123',
        'redirect_uri' => 'https://example.test/auth/github/callback',
    ]);
});

test('existing OAuth credentials are masked in the dashboard response', function () {
    $user = User::factory()->create();
    ProviderOAuthCredential::factory()->create([
        'user_id' => $user->id,
        'provider' => 'github',
        'client_id' => 'github-client-id',
        'client_secret' => 'super-secret-value-123',
        'redirect_uri' => 'https://example.test/auth/github/callback',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $maskedSecret = str_repeat('*', strlen('super-secret-value-123') - 8).substr('super-secret-value-123', -8);

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('provider_oauth_credentials.github.client_id', 'github-client-id')
        ->where('provider_oauth_credentials.github.client_secret', $maskedSecret)
        ->where('provider_oauth_credentials.github.redirect_uri', 'https://example.test/auth/github/callback'));
});