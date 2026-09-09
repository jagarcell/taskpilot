<?php

namespace Tests\Unit\Services;

use App\Models\AgentRun;
use App\Models\ProviderToken;
use App\Models\User;
use App\Services\Providers\CopilotAgentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('normalizes the Copilot request and response into the TaskPilot agent contract', function () {
    config()->set('services.copilot', [
        'token' => 'server-side-token',
        'base_uri' => 'https://api.githubcopilot.com',
        'model' => 'gpt-4o',
        'timeout' => 30,
    ]);

    Http::fake([
        'https://api.githubcopilot.com/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"summary":"Normalized Copilot output","status":"completed","analysis":{"likely_causes":["missing validation"]}}',
                    ],
                ],
            ],
        ], 200),
    ]);

    $run = AgentRun::factory()->create([
        'provider' => 'copilot',
        'model' => 'gpt-4o',
        'input' => ['prompt' => 'Investigate the login issue.'],
    ]);

    $payload = app(CopilotAgentProvider::class)->execute($run);

    expect($payload)->toMatchArray([
        'provider' => 'copilot',
        'model' => 'gpt-4o',
        'status' => 'completed',
        'summary' => 'Normalized Copilot output',
        'content' => [
            'summary' => 'Normalized Copilot output',
            'status' => 'completed',
            'analysis' => [
                'likely_causes' => ['missing validation'],
            ],
        ],
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://api.githubcopilot.com/chat/completions'
            && $body['model'] === 'gpt-4o'
            && $body['messages'][0]['role'] === 'system'
            && $body['messages'][1]['content'] === 'Investigate the login issue.';
    });
});

it('records the Copilot request and sanitized response in the audit log', function () {
    config()->set('services.copilot', [
        'token' => 'server-side-token',
        'base_uri' => 'https://api.githubcopilot.com',
        'model' => 'gpt-4o',
        'timeout' => 30,
    ]);

    Http::fake([
        'https://api.githubcopilot.com/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"summary":"Audit logged response","status":"completed"}',
                    ],
                ],
            ],
        ], 200),
    ]);

    Log::spy();

    $run = AgentRun::factory()->create([
        'provider' => 'copilot',
        'model' => 'gpt-4o',
        'input' => ['prompt' => 'Log audit output.'],
    ]);

    app(CopilotAgentProvider::class)->execute($run);

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []) {
        return str_contains($message, 'Copilot provider request')
            && isset($context['model'])
            && $context['model'] === 'gpt-4o'
            && $context['prompt'] === 'Log audit output.';
    })->once();

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []) {
        return str_contains($message, 'Copilot provider response')
            && isset($context['summary'])
            && $context['summary'] === 'Audit logged response';
    })->once();
});

it('prefers the stored github oauth token over the static copilot token when both are present', function () {
    config()->set('services.copilot', [
        'token' => 'server-side-token',
        'base_uri' => 'https://api.githubcopilot.com',
        'model' => 'gpt-4o',
        'timeout' => 30,
    ]);

    $user = User::factory()->create();
    ProviderToken::factory()->create([
        'user_id' => $user->id,
        'provider' => 'github',
        'access_token' => 'oauth-user-token',
        'refresh_token' => 'refresh-user-token',
        'token_type' => 'bearer',
        'scope' => 'read:user',
        'provider_user' => 'octocat',
        'expires_at' => now()->addHour(),
    ]);

    Http::fake([
        'https://api.githubcopilot.com/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"summary":"Authenticated Copilot output","status":"completed"}',
                    ],
                ],
            ],
        ], 200),
    ]);

    $run = AgentRun::factory()->create([
        'user_id' => $user->id,
        'provider' => 'copilot',
        'model' => 'gpt-4o',
        'input' => ['prompt' => 'Use my GitHub-authenticated Copilot session.'],
    ]);

    app(CopilotAgentProvider::class)->execute($run);

    Http::assertSent(function ($request) {
        $authorization = $request->hasHeader('Authorization')
            ? $request->header('Authorization')[0]
            : null;

        return $request->url() === 'https://api.githubcopilot.com/chat/completions'
            && $authorization === 'Bearer oauth-user-token';
    });
});

it('uses a stored github oauth token when the app has no static copilot token configured', function () {
    config()->set('services.copilot', [
        'token' => null,
        'base_uri' => 'https://api.githubcopilot.com',
        'model' => 'gpt-4o',
        'timeout' => 30,
    ]);

    $user = User::factory()->create();
    ProviderToken::factory()->create([
        'user_id' => $user->id,
        'provider' => 'github',
        'access_token' => 'oauth-user-token',
        'refresh_token' => 'refresh-user-token',
        'token_type' => 'bearer',
        'scope' => 'read:user',
        'provider_user' => 'octocat',
        'expires_at' => now()->addHour(),
    ]);

    Http::fake([
        'https://api.githubcopilot.com/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"summary":"Authenticated Copilot output","status":"completed"}',
                    ],
                ],
            ],
        ], 200),
    ]);

    $run = AgentRun::factory()->create([
        'user_id' => $user->id,
        'provider' => 'copilot',
        'model' => 'gpt-4o',
        'input' => ['prompt' => 'Use my GitHub-authenticated Copilot session.'],
    ]);

    app(CopilotAgentProvider::class)->execute($run);

    Http::assertSent(function ($request) {
        $authorization = $request->hasHeader('Authorization')
            ? $request->header('Authorization')[0]
            : null;

        return $request->url() === 'https://api.githubcopilot.com/chat/completions'
            && $authorization === 'Bearer oauth-user-token';
    });
});
