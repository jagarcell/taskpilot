<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\ProviderOAuthCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the authenticated dashboard with the current agent catalog.
     *
     * @return Response
     * Logic: provide the dashboard page with the list of agent definitions so users can manage activation and metadata.
     */
    public function index(): Response
    {
        $credentialsByProvider = ProviderOAuthCredential::query()
            ->where('user_id', auth()->id())
            ->get()
            ->mapWithKeys(fn (ProviderOAuthCredential $credential) => [
                $credential->provider => [
                    'provider' => $credential->provider,
                    'client_id' => $credential->client_id,
                    'client_secret' => $this->maskClientSecret($credential->client_secret),
                    'redirect_uri' => $credential->redirect_uri,
                ],
            ])
            ->all();

        return Inertia::render('dashboard', [
            'agents' => Agent::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Agent $agent) => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'slug' => $agent->slug,
                    'description' => $agent->description,
                    'provider' => $agent->provider,
                    'model' => $agent->model,
                    'is_active' => (bool) $agent->is_active,
                ])->all(),
            'provider_oauth_credentials' => $credentialsByProvider,
        ]);
    }

    /**
     * Persist a provider OAuth credential record for the authenticated user.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     * Logic: store the provider credential under the current user and preserve the existing secret when the field is passed back masked for display-only updates.
     */
    public function storeProviderOAuthCredentials(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:255'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'string', 'max:255'],
        ]);

        $provider = strtolower(trim((string) $validated['provider']));
        $credential = ProviderOAuthCredential::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', $provider)
            ->first();

        if ($credential === null) {
            $credential = new ProviderOAuthCredential();
        }

        $clientSecret = trim((string) $validated['client_secret']);

        if ($credential->exists && $this->isMaskedClientSecret($clientSecret)) {
            $existingMaskedSecret = $this->maskClientSecret((string) $credential->client_secret);

            if ($existingMaskedSecret === $clientSecret) {
                $clientSecret = (string) $credential->client_secret;
            }
        }

        $credential->fill([
            'user_id' => $request->user()->id,
            'provider' => $provider,
            'client_id' => trim((string) $validated['client_id']),
            'client_secret' => $clientSecret,
            'redirect_uri' => trim((string) $validated['redirect_uri']),
            'scope' => 'read:user user:email',
            'enabled' => true,
        ]);

        $credential->save();

        return Redirect::route('dashboard');
    }

    protected function maskClientSecret(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 8) {
            return str_repeat('*', max(0, strlen($value)));
        }

        return str_repeat('*', strlen($value) - 8).substr($value, -8);
    }

    protected function isMaskedClientSecret(string $value): bool
    {
        return $value !== '' && preg_match('/^\*+\S{8}$/', $value) === 1;
    }
}
