<?php

namespace App\Repositories;

use App\Models\ProviderToken;
use App\Models\User;

class ProviderTokenRepository
{
    /**
     * Fetch the most recent provider token for a user and provider.
     *
     * @param  User  $user
     * @param  string  $provider
     * @return ProviderToken|null
     * Logic: centralize the lookup of the active provider token so service classes no longer query the model relation directly.
     */
    public function findLatestForUser(User $user, string $provider): ?ProviderToken
    {
        return ProviderToken::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->orderByDesc('updated_at')
            ->first();
    }
}
