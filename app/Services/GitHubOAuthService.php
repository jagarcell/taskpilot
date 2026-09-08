<?php

namespace App\Services;

use App\Models\GitHubToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubOAuthService
{
    /**
     * Build the GitHub OAuth authorization URL for the current user session.
     *
     * @param  string|null  $state
     * @return string
     * Logic: construct the server-side GitHub OAuth URL that requests the minimal read-only identity scopes needed to obtain a usable GitHub token for Copilot access.
     */
    public function authorizationUrl(?string $state = null): string
    {
        $clientId = trim((string) config('services.github.client_id'));
        $redirectUri = trim((string) config('services.github.redirect'));

        if ($clientId === '' || $redirectUri === '') {
            throw new RuntimeException('GitHub OAuth client configuration is missing.');
        }

        $parameters = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'read:user user:email',
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
     * @return GitHubToken
     * Logic: complete the OAuth code exchange server-side so the app can store a valid GitHub user token without exposing it in browser state.
     */
    public function exchangeCode(User $user, string $code): GitHubToken
    {
        $clientId = trim((string) config('services.github.client_id'));
        $clientSecret = trim((string) config('services.github.client_secret'));
        $redirectUri = trim((string) config('services.github.redirect'));

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new RuntimeException('GitHub OAuth credentials are not configured.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('GitHub OAuth exchange failed.');
        }

        $payload = $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('GitHub OAuth did not return an access token.');
        }

        $existing = $user->githubToken()->where('provider', 'github')->first();
        $token = $existing ?? new GitHubToken(['user_id' => $user->id, 'provider' => 'github']);

        $token->fill([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => $accessToken,
            'refresh_token' => (string) ($payload['refresh_token'] ?? $token->refresh_token ?? ''),
            'token_type' => (string) ($payload['token_type'] ?? 'bearer'),
            'scope' => (string) ($payload['scope'] ?? $token->scope ?? ''),
            'github_user' => (string) ($payload['user']['login'] ?? $token->github_user ?? ''),
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
        $token = $user->githubToken()->where('provider', 'github')->first();

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
     * @return GitHubToken|null
     * Logic: refresh GitHub OAuth credentials without exposing secrets in the client or issue history.
     */
    public function refreshToken(User $user): ?GitHubToken
    {
        $token = $user->githubToken()->where('provider', 'github')->first();

        if ($token === null || trim((string) $token->refresh_token) === '') {
            return null;
        }

        $clientId = trim((string) config('services.github.client_id'));
        $clientSecret = trim((string) config('services.github.client_secret'));

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
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
