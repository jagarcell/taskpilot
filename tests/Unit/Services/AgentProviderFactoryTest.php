<?php

namespace Tests\Unit\Services;

use App\Services\AgentProviderFactory;
use App\Services\Providers\CopilotAgentProvider;
use App\Services\Providers\OpenAiAgentProvider;
use InvalidArgumentException;
use Tests\TestCase;

it('supports the openai provider contract and normalizes it case-insensitively', function () {
    expect(AgentProviderFactory::supportedProviders())->toContain('openai');
    expect(AgentProviderFactory::normalizeProvider('OpenAI'))->toBe('openai');
    expect(AgentProviderFactory::normalizeProvider(null))->toBe('openai');

    $factory = new AgentProviderFactory();
    expect($factory->resolve('OPENAI'))->toBeInstanceOf(OpenAiAgentProvider::class);
});

it('supports the copilot provider contract and normalizes it case-insensitively', function () {
    expect(AgentProviderFactory::supportedProviders())->toContain('copilot');
    expect(AgentProviderFactory::normalizeProvider('Copilot'))->toBe('copilot');

    $factory = new AgentProviderFactory();
    expect($factory->resolve('COPILOT'))->toBeInstanceOf(CopilotAgentProvider::class);
});

it('exposes copilot credentials and provider settings only through the server-side config contract', function () {
    config()->set('services.copilot', [
        'token' => 'server-side-token',
        'base_uri' => 'https://api.githubcopilot.com',
        'model' => 'gpt-4o',
        'timeout' => 30,
    ]);

    expect(config('services.copilot.token'))->toBe('server-side-token');
    expect(config('services.copilot.base_uri'))->toBe('https://api.githubcopilot.com');
    expect(config('services.copilot.model'))->toBe('gpt-4o');
    expect(config('services.copilot.timeout'))->toBe(30);
});

it('throws for unsupported providers', function () {
    $factory = new AgentProviderFactory();

    $factory->resolve('anthropic');
})->throws(InvalidArgumentException::class, 'Unsupported agent provider: anthropic.');
