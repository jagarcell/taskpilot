<?php

namespace App\Services\ConnectionTesting;

use App\Contracts\ConnectionTester;

class OpenAiConnectionTester implements ConnectionTester
{
    /**
     * Simulate a connection test for the OpenAI provider.
     *
     * @return array<string, mixed>
     * Logic: keep the dashboard contract stable even though OpenAI is not configured for a live upstream check in this app; the mock result is enough to exercise the provider-agnostic UI contract.
     */
    public function testConnection(): array
    {
        return [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'status' => 'ok',
            'summary' => 'OpenAI mock connection test succeeded. This is a simulated provider check for the current app setup.',
            'credential_status' => 'mock',
        ];
    }

    /**
     * Return a no-op reauthentication payload for the mock provider.
     *
     * @return array<string, mixed>
     * Logic: preserve a provider-agnostic contract for future providers while OpenAI remains a mock-only integration in this app.
     */
    public function reauthAction(): array
    {
        return [
            'provider' => 'openai',
            'status' => 'mock',
            'summary' => 'OpenAI is running in mock mode and does not require external OAuth reauthentication.',
            'reauth_required' => false,
        ];
    }
}
