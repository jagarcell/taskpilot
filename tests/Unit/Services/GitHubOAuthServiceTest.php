<?php

namespace Tests\Unit\Services;

use App\Models\ProviderOAuthCredential;
use App\Models\User;
use App\Services\GitHubOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('uses the database OAuth credential record for GitHub authorization URLs', function () {
    ProviderOAuthCredential::factory()->create([
        'provider' => 'github',
        'client_id' => 'db-client-id',
        'client_secret' => 'db-client-secret',
        'redirect_uri' => 'https://tenant.example.test/auth/github/callback',
        'scope' => 'read:user user:email',
    ]);

    config()->set('services.github.client_id', 'env-client-id');
    config()->set('services.github.redirect', 'https://env.example.test/auth/github/callback');

    $url = app(GitHubOAuthService::class)->authorizationUrl('tenant-state');

    expect($url)
        ->toContain('client_id=db-client-id')
        ->toContain('redirect_uri=https%3A%2F%2Ftenant.example.test%2Fauth%2Fgithub%2Fcallback')
        ->toContain('state=tenant-state');
});

it('exchanges OAuth codes with the database credential record instead of env secrets', function () {
    $user = User::factory()->create();

    ProviderOAuthCredential::factory()->create([
        'provider' => 'github',
        'client_id' => 'db-client-id',
        'client_secret' => 'db-client-secret',
        'redirect_uri' => 'https://tenant.example.test/auth/github/callback',
        'scope' => 'read:user user:email',
    ]);

    config()->set('services.github.client_id', 'env-client-id');
    config()->set('services.github.client_secret', 'env-client-secret');
    config()->set('services.github.redirect', 'https://env.example.test/auth/github/callback');

    Http::fake([
        'https://github.com/login/oauth/access_token' => Http::response([
            'access_token' => 'oauth-token-from-db',
            'token_type' => 'bearer',
            'scope' => 'read:user',
            'expires_in' => 3600,
            'user' => ['login' => 'octocat'],
        ], 200),
    ]);

    $token = app(GitHubOAuthService::class)->exchangeCode($user, 'tenant-auth-code');

    expect($token->access_token)->toBe('oauth-token-from-db');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://github.com/login/oauth/access_token'
            && ($body['client_id'] ?? null) === 'db-client-id'
            && ($body['client_secret'] ?? null) === 'db-client-secret'
            && ($body['redirect_uri'] ?? null) === 'https://tenant.example.test/auth/github/callback';
    });
});
