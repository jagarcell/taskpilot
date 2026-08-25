<?php

namespace App\Services;

use App\Contracts\AgentProvider;
use App\Services\Providers\OpenAiAgentProvider;
use InvalidArgumentException;

class AgentProviderFactory
{
    /**
     * Return the supported provider identifiers for this application.
     *
     * @return array<int, string>
     * Logic: centralize the supported provider catalog so validation and runtime resolution use the same source of truth.
     */
    public static function supportedProviders(): array
    {
        return ['openai'];
    }

    /**
     * Normalize a provider identifier for validation and resolution.
     *
     * @param  string|null  $provider
     * @return string
     * Logic: treat provider names case-insensitively while preserving the single canonical vendor value used in the runtime contract.
     */
    public static function normalizeProvider(?string $provider): string
    {
        return strtolower(trim((string) ($provider ?? 'openai')) ?: 'openai');
    }

    /**
     * Resolve a provider implementation for the configured agent vendor.
     *
     * @param  string|null  $provider
     * @return AgentProvider
     * Logic: keep provider selection centralized behind a single factory so the execution layer remains provider-agnostic.
     */
    public function resolve(?string $provider): AgentProvider
    {
        $normalizedProvider = self::normalizeProvider($provider);

        return match ($normalizedProvider) {
            'openai' => app(OpenAiAgentProvider::class),
            default => throw new InvalidArgumentException("Unsupported agent provider: {$provider}."),
        };
    }
}
