<?php

namespace App\Services;

use App\Models\ProviderOAuthCredential;
use App\Models\ProviderToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubOAuthService
{
    /**
     * Resolve the active GitHub OAuth client settings from the database-backed provider credential table.
     *
     * @return array{client_id: string, client_secret: string, redirect_uri: string, scope: string}
     * Logic: keep all GitHub OAuth secrets in the database so credentials are tenant-safe and can be managed without app redeploys.
     */
    protected function resolveCredentials(): array
    {
        /** @var ProviderOAuthCredential|null $credential */
        $credential = ProviderOAuthCredential::query()
            ->where('provider', 'github')
            ->where('enabled', true)
            ->first();

        if ($credential === null) {
            throw new RuntimeException('GitHub OAuth credentials are not configured.');
        }

        $clientId = trim((string) $credential->client_id);
        $clientSecret = trim((string) $credential->client_secret);
        $redirectUri = trim((string) $credential->redirect_uri);

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new RuntimeException('GitHub OAuth credentials are incomplete.');
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'scope' => trim((string) ($credential->scope ?: 'read:user user:email')),
        ];
    }

    /**
     * Build the GitHub OAuth authorization URL for the current user session.
     *
     * @param  string|null  $state
     * @return string
     * Logic: construct the server-side GitHub OAuth URL that requests the minimal read-only identity scopes needed to obtain a usable GitHub token for Copilot access.
     */
    public function authorizationUrl(?string $state = null): string
    {
        $credentials = $this->resolveCredentials();

        $parameters = [
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $credentials['redirect_uri'],
            'scope' => $credentials['scope'] !== '' ? $credentials['scope'] : 'read:user user:email',
            'allow_signup' => 'true',
        ];

        if ($state !== null && $state !== '') {
            $parameters['state'] = $state;
        }

        return 'https://github.com/login/oauth/authorize?'.http_build_query($parameters);
    }

    /**
     * Exchange a GitHub OAuth code for a user access token and persist it for the authenticated user.
     *
     * @param  User  $user
     * @param  string  $code
     * @return ProviderToken
     * Logic: complete the OAuth code exchange server-side so the app can store a valid GitHub user token without exposing it in browser state.
     */
    public function exchangeCode(User $user, string $code): ProviderToken
    {
        $credentials = $this->resolveCredentials();

        $response = Http::asForm()
            ->acceptJson()
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'code' => $code,
                'redirect_uri' => $credentials['redirect_uri'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('GitHub OAuth exchange failed.');
        }

        $payload = $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('GitHub OAuth did not return an access token.');
        }

        $existing = $user->providerToken()->where('provider', 'github')->first();
        $token = $existing ?? new ProviderToken(['user_id' => $user->id, 'provider' => 'github']);

        $token->fill([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => $accessToken,
            'refresh_token' => (string) ($payload['refresh_token'] ?? $token->refresh_token ?? ''),
            'token_type' => (string) ($payload['token_type'] ?? 'bearer'),
            'scope' => (string) ($payload['scope'] ?? $token->scope ?? $credentials['scope']),
            'provider_user' => (string) ($payload['user']['login'] ?? $token->provider_user ?? ''),
            'expires_at' => isset($payload['expires_in']) && is_numeric($payload['expires_in'])
                ? now()->addSeconds((int) $payload['expires_in'])
                : $token->expires_at,
        ]);

        $token->save();

        return $token;
    }

    /**
     * Return the current valid GitHub OAuth token for a user, if any.
     *
     * @param  User  $user
     * @return string|null
     * Logic: prefer a valid stored GitHub OAuth token so Copilot requests can use user-scoped access instead of a static environment secret.
     */
    public function getValidToken(User $user): ?string
    {
        $token = $user->providerToken()->where('provider', 'github')->first();

        if ($token === null) {
            return null;
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            $refreshed = $this->refreshToken($user);

            if ($refreshed !== null) {
                return $refreshed->access_token;
            }

            return null;
        }

        return $token->access_token;
    }

    /**
     * Refresh an expired GitHub OAuth token for the user.
     *
     * @param  User  $user
     * @return ProviderToken|null
     * Logic: refresh GitHub OAuth credentials without exposing secrets in the client or issue history.
     */
    public function refreshToken(User $user): ?ProviderToken
    {
        $token = $user->providerToken()->where('provider', 'github')->first();

        if ($token === null || trim((string) $token->refresh_token) === '') {
            return null;
        }

        $credentials = $this->resolveCredentials();

        $response = Http::asForm()
            ->acceptJson()
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refresh_token,
            ]);

        if ($response->failed()) {
            return null;
        }

        $payload = $response->json();
        $newAccessToken = (string) ($payload['access_token'] ?? '');

        if ($newAccessToken === '') {
            return null;
        }

        $token->fill([
            'access_token' => $newAccessToken,
            'refresh_token' => (string) ($payload['refresh_token'] ?? $token->refresh_token),
            'token_type' => (string) ($payload['token_type'] ?? $token->token_type),
            'scope' => (string) ($payload['scope'] ?? $token->scope),
            'expires_at' => isset($payload['expires_in']) && is_numeric($payload['expires_in'])
                ? now()->addSeconds((int) $payload['expires_in'])
                : $token->expires_at,
        ]);

        $token->save();

        return $token;
    }
}
