<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\ProviderOAuthCredential;
use App\Models\RepositoryToken;
use App\Models\User;
use App\Services\GitHubOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('requests repo access even when an older stored scope is missing it', function () {
    ProviderOAuthCredential::factory()->create([
        'provider' => 'github',
        'client_id' => 'db-client-id',
        'client_secret' => 'db-client-secret',
        'redirect_uri' => url('/projects/repository/oauth/callback'),
        'scope' => 'read:user user:email',
    ]);

    $url = app(GitHubOAuthService::class)->authorizationUrl('tenant-state');

    expect($url)->toContain('scope=read%3Auser+user%3Aemail+repo');
});

it('uses the configured GitHub callback URI for authorization URLs when no override is supplied', function () {
    $projectCallback = url('/projects/repository/oauth/callback');

    ProviderOAuthCredential::factory()->create([
        'provider' => 'github',
        'client_id' => 'db-client-id',
        'client_secret' => 'db-client-secret',
        'redirect_uri' => $projectCallback,
        'scope' => 'read:user user:email repo',
    ]);

    config()->set('services.github.client_id', 'env-client-id');
    config()->set('services.github.redirect', 'https://env.example.test/auth/github/callback');

    $url = app(GitHubOAuthService::class)->authorizationUrl('tenant-state');

    expect($url)
        ->toContain('client_id=db-client-id')
        ->toContain('redirect_uri='.urlencode($projectCallback))
        ->toContain('state=tenant-state');
});

it('uses the dedicated project repository callback when creating the project OAuth authorization URL', function () {
    ProviderOAuthCredential::factory()->create([
        'provider' => 'github',
        'client_id' => 'db-client-id',
        'client_secret' => 'db-client-secret',
        'redirect_uri' => 'https://tenant.example.test/auth/github/callback',
        'scope' => 'read:user user:email repo',
    ]);

    $url = app(GitHubOAuthService::class)->authorizationUrl('project-state', 'https://tenant.example.test/projects/repository/oauth/callback');

    expect($url)
        ->toContain('client_id=db-client-id')
        ->toContain('redirect_uri=https%3A%2F%2Ftenant.example.test%2Fprojects%2Frepository%2Foauth%2Fcallback')
        ->toContain('state=project-state');
});

it('exchanges OAuth codes with the database credential record instead of env secrets', function () {
    $user = User::factory()->create();
    $projectCallback = url('/projects/repository/oauth/callback');

    ProviderOAuthCredential::factory()->create([
        'provider' => 'github',
        'client_id' => 'db-client-id',
        'client_secret' => 'db-client-secret',
        'redirect_uri' => $projectCallback,
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

    Http::assertSent(function ($request) use ($projectCallback) {
        $body = $request->data();

        return $request->url() === 'https://github.com/login/oauth/access_token'
            && ($body['client_id'] ?? null) === 'db-client-id'
            && ($body['client_secret'] ?? null) === 'db-client-secret'
            && ($body['redirect_uri'] ?? null) === $projectCallback;
    });
});

it('stores a project-scoped GitHub OAuth token for the active user and project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    ProviderOAuthCredential::factory()->create([
        'provider' => 'github',
        'client_id' => 'db-client-id',
        'client_secret' => 'db-client-secret',
        'redirect_uri' => 'https://tenant.example.test/projects/'.$project->id.'/repository/oauth/callback',
        'scope' => 'read:user user:email repo',
    ]);

    Http::fake([
        'https://github.com/login/oauth/access_token' => Http::response([
            'access_token' => 'project-github-token',
            'token_type' => 'bearer',
            'scope' => 'repo read:user',
            'expires_in' => 7200,
            'user' => ['login' => 'octocat'],
        ], 200),
    ]);

    $token = app(GitHubOAuthService::class)->exchangeCodeForProject($user, $project, 'project-auth-code');

    expect($token)->toBeInstanceOf(RepositoryToken::class)
        ->and($token->user_id)->toBe($user->id)
        ->and($token->project_id)->toBe($project->id)
        ->and($token->provider)->toBe('github')
        ->and($token->access_token)->toBe('project-github-token')
        ->and($token->provider_user)->toBe('octocat')
        ->and(RepositoryToken::query()->where('user_id', $user->id)->where('project_id', $project->id)->where('provider', 'github')->count())->toBe(1);
});

it('validates the project OAuth callback and persists the project token from the service layer', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    session()->put('project_github_oauth_state', $project->id.':abc123');
    session()->put('project_github_oauth_project_id', $project->id);

    ProviderOAuthCredential::factory()->create([
        'provider' => 'github',
        'client_id' => 'db-client-id',
        'client_secret' => 'db-client-secret',
        'redirect_uri' => 'https://tenant.example.test/projects/repository/oauth/callback',
        'scope' => 'read:user user:email repo',
    ]);

    Http::fake([
        'https://github.com/login/oauth/access_token' => Http::response([
            'access_token' => 'service-layer-project-token',
            'token_type' => 'bearer',
            'scope' => 'repo read:user',
            'expires_in' => 7200,
            'user' => ['login' => 'octocat'],
        ], 200),
    ]);

    $request = Request::create('/projects/repository/oauth/callback?state='.$project->id.':abc123&code=project-auth-code');

    $resolvedProject = app(GitHubOAuthService::class)->handleProjectCallback($request, $user);

    expect($resolvedProject->id)->toBe($project->id)
        ->and(RepositoryToken::query()->where('user_id', $user->id)->where('project_id', $project->id)->where('provider', 'github')->first()?->access_token)->toBe('service-layer-project-token')
        ->and(session('project_github_oauth_state'))->toBeNull()
        ->and(session('project_github_oauth_project_id'))->toBeNull();
});
