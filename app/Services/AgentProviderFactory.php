<?php

namespace App\Services;

use App\Contracts\AgentProvider;
use App\Services\Providers\OpenAiAgentProvider;
use InvalidArgumentException;

class AgentProviderFactory
{
    /**
     * Resolve a provider implementation for the configured agent vendor.
     *
     * @param  string|null  $provider
     * @return AgentProvider
     * Logic: keep provider selection centralized behind a single factory so the execution layer remains provider-agnostic.
     */
    public function resolve(?string $provider): AgentProvider
    {
        $normalizedProvider = strtolower($provider ?? 'openai');

        return match ($normalizedProvider) {
            'openai' => app(OpenAiAgentProvider::class),
            default => throw new InvalidArgumentException("Unsupported agent provider: {$provider}."),
        };
    }
}
