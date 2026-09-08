<?php

namespace App\Services\ConnectionTesting;

use App\Contracts\ConnectionTester;
use App\Services\GitHubOAuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class CopilotConnectionTester implements ConnectionTester
{
    /**
     * Validate that Copilot is reachable using the current OAuth-backed token flow.
     *
     * @return array<string, mixed>
     * Logic: prefer the stored user-scoped GitHub OAuth token and fall back to a server-side configured token for the provider test so the dashboard uses the same contract as the real provider execution path.
     */
    public function testConnection(): array
    {
        $token = null;
        $model = (string) config('services.copilot.model', 'gpt-4o');
        $user = Auth::user();

        if ($user !== null) {
            $token = app(GitHubOAuthService::class)->getValidToken($user);
        }

        if (blank($token)) {
            $token = config('services.copilot.token');
        }

        if (blank($token)) {
            return [
                'provider' => 'copilot',
                'model' => $model,
                'status' => 'missing_credentials',
                'summary' => 'Copilot access is not configured. Connect GitHub OAuth or add a server-side COPILOT_API_TOKEN.',
                'reauth_required' => false,
            ];
        }

        $endpoint = rtrim((string) config('services.copilot.base_uri', 'https://api.githubcopilot.com'), '/');

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('services.copilot.timeout', 30))
            ->post($endpoint . '/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Validate TaskPilot connectivity. Respond briefly to confirm access is available.'],
                    ['role' => 'user', 'content' => 'TaskPilot connectivity check: confirm Copilot access is available.'],
                ],
                'temperature' => 0.1,
            ]);

        if ($response->failed()) {
            $message = (string) $response->json('message', 'Copilot request failed.');
            $statusCode = $response->status();
            $reauthRequired = $statusCode === 401 || $statusCode === 403 || str_contains(strtolower($message), 'expired') || str_contains(strtolower($message), 'token');

            return [
                'provider' => 'copilot',
                'model' => $model,
                'status' => 'failed',
                'summary' => $reauthRequired
                    ? 'Copilot access failed because the stored GitHub OAuth token was rejected or expired. Re-authorizing with GitHub now so the app can obtain a fresh Copilot session.'
                    : 'Copilot access test failed; the configured token could not complete the request.',
                'reauth_required' => $reauthRequired,
                'errors' => [
                    'message' => $message,
                    'status' => $statusCode,
                ],
            ];
        }

        return [
            'provider' => 'copilot',
            'model' => $model,
            'status' => 'ok',
            'summary' => 'Copilot access confirmed for the configured GitHub account.',
            'reauth_required' => false,
        ];
    }

    /**
     * Return the reauthentication flow for Copilot-backed GitHub OAuth access.
     *
     * @return array<string, mixed>
     * Logic: expose the provider-specific reconnect action through the common contract so the dashboard can trigger a generic reauth flow for any supported provider.
     */
    public function reauthAction(): array
    {
        return [
            'provider' => 'copilot',
            'status' => 'reauth_required',
            'summary' => 'Reconnect GitHub OAuth to refresh the Copilot access token.',
            'reauth_required' => true,
            'redirect_to' => route('github.oauth.authorize'),
        ];
    }
}
