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
}
