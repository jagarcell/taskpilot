<?php

namespace Tests\Unit\Services;

use App\Services\AgentProviderFactory;
use App\Services\Providers\OpenAiAgentProvider;
use InvalidArgumentException;
use Tests\TestCase;

it('supports the openai provider contract and normalizes it case-insensitively', function () {
    expect(AgentProviderFactory::supportedProviders())->toBe(['openai']);
    expect(AgentProviderFactory::normalizeProvider('OpenAI'))->toBe('openai');
    expect(AgentProviderFactory::normalizeProvider(null))->toBe('openai');

    $factory = new AgentProviderFactory();
    expect($factory->resolve('OPENAI'))->toBeInstanceOf(OpenAiAgentProvider::class);
});

it('throws for unsupported providers', function () {
    $factory = new AgentProviderFactory();

    $factory->resolve('anthropic');
})->throws(InvalidArgumentException::class, 'Unsupported agent provider: anthropic.');
