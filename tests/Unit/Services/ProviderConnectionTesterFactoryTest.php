<?php

namespace Tests\Unit\Services;

use App\Services\ConnectionTesting\CopilotConnectionTester;
use App\Services\ConnectionTesting\OpenAiConnectionTester;
use App\Services\ProviderConnectionTesterFactory;
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
