<?php

namespace App\Http\Controllers;

use App\Services\GitHubOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GitHubOAuthController extends Controller
{
    public function __construct(
        protected GitHubOAuthService $githubOAuthService,
    ) {}

    /**
     * Redirect the authenticated user to GitHub for OAuth consent.
     *
     * @return RedirectResponse
     * Logic: start the GitHub OAuth flow from the app so the user can grant access without storing credentials in the browser.
     */
    public function authorize(): RedirectResponse
    {
        $state = bin2hex(random_bytes(16));
        session(['github_oauth_state' => $state]);

        return redirect()->away($this->githubOAuthService->authorizationUrl($state));
    }

    /**
     * Exchange the GitHub OAuth callback for an access token and persist it for the user.
     *
     * @param  Request  $request
     * @return RedirectResponse
     * Logic: validate the GitHub callback state, trade the code for a token, and save the access token server-side for Copilot requests.
     */
    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state');
        $savedState = (string) session('github_oauth_state', '');

        if ($savedState !== '' && $state !== '' && $state !== $savedState) {
            abort(419, 'GitHub OAuth state mismatch.');
        }

        if ($request->query('error')) {
            abort(400, (string) $request->query('error_description', 'GitHub OAuth authorization failed.'));
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            abort(400, 'GitHub OAuth callback did not include a code.');
        }

        $user = Auth::user();

        if ($user === null) {
            abort(401, 'Authentication required to connect GitHub OAuth.');
        }

        $this->githubOAuthService->exchangeCode($user, $code);

        return redirect()->route('dashboard')->with('status', 'GitHub OAuth connected successfully.');
    }
}
