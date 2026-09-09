<?php

namespace App\Contracts;

interface ConnectionTester
{
    /**
     * Validate that the configured provider can reach its upstream API.
     *
     * @return array<string, mixed>
     * Logic: standardize provider connectivity checks so the dashboard and future integrations can share one contract regardless of backend details.
     */
    public function testConnection(): array;

    /**
     * Return the provider's currently available model identifiers.
     *
     * @return array<int, string>
     * Logic: allow the dashboard and agent configuration UI to discover the active model catalog without maintaining a static client-side list.
     */
    public function availableModels(): array;

    /**
     * Return the reauthentication URL or action payload for the provider.
     *
     * @return array<string, mixed>
     * Logic: allow provider-specific reauth flows to be requested through a common contract without hard-coding GitHub into the dashboard UI.
     */
    public function reauthAction(): array;
}
