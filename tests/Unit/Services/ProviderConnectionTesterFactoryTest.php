<?php

namespace Tests\Unit\Services;

use App\Services\ConnectionTesting\CopilotConnectionTester;
use App\Services\ConnectionTesting\OpenAiConnectionTester;
use App\Services\ProviderConnectionTesterFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

it('resolves supported provider connection testers case-insensitively', function () {
    $factory = new ProviderConnectionTesterFactory();

    expect($factory->resolve('openai'))->toBeInstanceOf(OpenAiConnectionTester::class);
    expect($factory->resolve('OpenAI'))->toBeInstanceOf(OpenAiConnectionTester::class);
    expect($factory->resolve('copilot'))->toBeInstanceOf(CopilotConnectionTester::class);
    expect($factory->resolve('COPILOT'))->toBeInstanceOf(CopilotConnectionTester::class);
});

it('returns a mock successful payload for the openai provider tester', function () {
    $tester = (new ProviderConnectionTesterFactory())->resolve('openai');

    $result = $tester->testConnection();

    expect($result['provider'])->toBe('openai');
    expect($result['status'])->toBe('ok');
    expect($result['summary'])->toContain('mock');
});

it('discovers the live Copilot model list from the provider API when configured', function () {
    config()->set('services.copilot', [
        'token' => 'copilot-token',
        'base_uri' => 'https://api.githubcopilot.com',
        'model' => 'gpt-4o',
        'timeout' => 30,
    ]);

    Http::fake([
        'https://api.githubcopilot.com/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4o'],
                ['id' => 'gpt-4o-mini'],
            ],
        ], 200),
    ]);

    $tester = (new ProviderConnectionTesterFactory())->resolve('copilot');

    expect($tester->availableModels())->toBe(['gpt-4o', 'gpt-4o-mini']);
});
