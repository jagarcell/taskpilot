<?php

namespace App\Repositories;

use App\Models\ProviderOAuthCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ProviderOAuthCredentialRepository
{
    /**
     * Fetch the provider credential rows for a user.
     *
     * @param  User  $user
     * @return Collection<int, ProviderOAuthCredential>
     * Logic: return the credential records for the authenticated user so the dashboard may display masked values without direct model queries in the controller.
     */
    public function getForUser(User $user): Collection
    {
        return ProviderOAuthCredential::query()
            ->where('user_id', $user->id)
            ->get();
    }

    /**
     * Fetch the active credential row for a provider.
     *
     * @param  string  $provider
     * @return ProviderOAuthCredential|null
     * Logic: resolve the enabled provider credential from the persisted record so service code does not reach into the model query builder directly.
     */
    public function findEnabledForProvider(string $provider): ?ProviderOAuthCredential
    {
        return ProviderOAuthCredential::query()
            ->where('provider', $provider)
            ->where('enabled', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }
}
