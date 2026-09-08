<?php

namespace App\Services;

use App\Contracts\ConnectionTester;
use App\Services\ConnectionTesting\CopilotConnectionTester;
use App\Services\ConnectionTesting\OpenAiConnectionTester;
use InvalidArgumentException;

class ProviderConnectionTesterFactory
{
    /**
     * Return the supported provider identifiers for connectivity checks.
     *
     * @return array<int, string>
     * Logic: keep connectivity-check coverage aligned with the supported provider catalog so future providers can be added in one place.
     */
    public static function supportedProviders(): array
    {
        return ['openai', 'copilot'];
    }

    /**
     * Normalize a provider identifier for connectivity testing.
     *
     * @param  string|null  $provider
     * @return string
     * Logic: canonicalize provider names before resolution so UI and backend names remain case-insensitive.
     */
    public static function normalizeProvider(?string $provider): string
    {
        return strtolower(trim((string) ($provider ?? 'openai')) ?: 'openai');
    }

    /**
     * Resolve a connection tester for the configured provider.
     *
     * @param  string|null  $provider
     * @return ConnectionTester
     * Logic: centralize provider-specific test implementations behind a single factory so new integrations can plug in without changing controller or UI code.
     */
    public function resolve(?string $provider): ConnectionTester
    {
        $normalizedProvider = self::normalizeProvider($provider);

        return match ($normalizedProvider) {
            'openai' => app(OpenAiConnectionTester::class),
            'copilot' => app(CopilotConnectionTester::class),
            default => throw new InvalidArgumentException("Unsupported provider for connection testing: {$provider}."),
        };
    }
}
