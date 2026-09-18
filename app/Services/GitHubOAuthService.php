<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProviderOAuthCredential;
use App\Models\ProviderToken;
use App\Models\RepositoryToken;
use App\Models\User;
use App\Repositories\ProjectRepository;
use App\Repositories\ProviderOAuthCredentialRepository;
use App\Repositories\ProviderTokenRepository;
use App\Repositories\RepositoryTokenRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubOAuthService
{
    public function __construct(
        protected ProviderOAuthCredentialRepository $providerOAuthCredentialRepository,
        protected ProjectRepository $projectRepository,
        protected RepositoryTokenRepository $repositoryTokenRepository,
        protected ProviderTokenRepository $providerTokenRepository,
    ) {}

    /**
     * Resolve the active GitHub OAuth client settings from the database-backed provider credential table.
     *
     * @return array{client_id: string, client_secret: string, redirect_uri: string, scope: string}
     * Logic: keep all GitHub OAuth secrets in the database so credentials are tenant-safe and can be managed without app redeploys.
     */
    protected function resolveCredentials(): array
    {
        /** @var ProviderOAuthCredential|null $credential */
        $credential = $this->providerOAuthCredentialRepository->findEnabledForProvider('github');

        if ($credential === null) {
            throw new RuntimeException('GitHub OAuth credentials are not configured.');
        }

        $clientId = trim((string) $credential->client_id);
        $clientSecret = trim((string) $credential->client_secret);
        $redirectUri = trim((string) $credential->redirect_uri);

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new RuntimeException('GitHub OAuth credentials are incomplete.');
        }

        $scope = trim((string) ($credential->scope ?: 'read:user user:email repo'));
        $normalizedScope = preg_split('/\s+/', $scope, -1, PREG_SPLIT_NO_EMPTY);
        $normalizedScope = array_values(array_unique($normalizedScope));

        if (! in_array('repo', $normalizedScope, true)) {
            $normalizedScope[] = 'repo';
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $normalizedScope),
        ];
    }

    /**
     * Build the GitHub OAuth authorization URL for the current user session.
     *
     * @param  string|null  $state
     * @param  string|null  $redirectUri
     * @return string
     * Logic: construct the server-side GitHub OAuth URL using the configured callback unless a dedicated project-specific callback is explicitly required.
     */
    public function authorizationUrl(?string $state = null, ?string $redirectUri = null): string
    {
        $credentials = $this->resolveCredentials();

        $parameters = [
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $redirectUri !== null && trim($redirectUri) !== '' ? trim($redirectUri) : $credentials['redirect_uri'],
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
        $redirectUri = url('/projects/repository/oauth/callback');

        $response = Http::asForm()
            ->acceptJson()
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
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
     * Validate a project OAuth callback request and persist the resulting project-scoped token.
     *
     * @param  Request  $request
     * @param  User  $user
     * @return Project
     * Logic: centralize the project OAuth validation rules in the service so the controller remains a thin route adapter and the callback is tested as a single business operation.
     */
    public function handleProjectCallback(Request $request, User $user): Project
    {
        $state = (string) $request->query('state');
        $savedState = (string) session('project_github_oauth_state', '');

        if ($savedState !== '' && $state !== '' && $state !== $savedState) {
            abort(419, 'GitHub project OAuth state mismatch.');
        }

        if ($request->query('error')) {
            abort(400, (string) $request->query('error_description', 'GitHub project OAuth authorization failed.'));
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            abort(400, 'GitHub project OAuth callback did not include a code.');
        }

        $projectId = (int) session('project_github_oauth_project_id', 0);

        if ($projectId <= 0 && preg_match('/^(\d+):/', $state, $matches) === 1) {
            $projectId = (int) $matches[1];
        }

        $project = $projectId > 0 ? $this->projectRepository->findById($projectId) : null;

        session()->forget(['project_github_oauth_state', 'project_github_oauth_project_id']);

        if ($project === null) {
            throw new RuntimeException('Project repository OAuth session expired. Please retry the connection.');
        }

        $this->exchangeCodeForProject($user, $project, $code);

        return $project;
    }

    /**
     * Exchange a GitHub OAuth code for a project-scoped repository access token.
     *
     * @param  User  $user
     * @param  Project  $project
     * @param  string  $code
     * @return RepositoryToken
     * Logic: persist the token server-side for the authenticated user and the project that is connecting a remote repository so private repo access remains scoped and auditable.
     */
    public function exchangeCodeForProject(User $user, Project $project, string $code): RepositoryToken
    {
        $credentials = $this->resolveCredentials();
        $redirectUri = url('/projects/repository/oauth/callback');

        $response = Http::asForm()
            ->acceptJson()
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('GitHub project OAuth exchange failed.');
        }

        $payload = $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('GitHub project OAuth did not return an access token.');
        }

        $existing = $this->repositoryTokenRepository->findLatestForProjectUser($project, $user, 'github');

        $token = $existing ?? new RepositoryToken([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'provider' => 'github',
        ]);

        $token->fill([
            'user_id' => $user->id,
            'project_id' => $project->id,
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
        $token = $this->providerTokenRepository->findLatestForUser($user, 'github');

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
        $token = $this->providerTokenRepository->findLatestForUser($user, 'github');

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
