<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\RepositoryToken;
use App\Models\User;

class RepositoryTokenRepository
{
    /**
     * Fetch the most recent project-scoped token for a provider and user.
     *
     * @param  Project  $project
     * @param  User  $user
     * @param  string  $provider
     * @return RepositoryToken|null
     * Logic: resolve the active project token through the repository layer so direct token queries are not scattered across services.
     */
    public function findLatestForProjectUser(Project $project, User $user, string $provider): ?RepositoryToken
    {
        return RepositoryToken::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Fetch the most recent token for a user and provider outside the project scope.
     *
     * @param  User  $user
     * @param  string  $provider
     * @return RepositoryToken|null
     * Logic: return the user-scoped provider token when no project-bound token is available, keeping the lookup logic in one place.
     */
    public function findLatestForUser(User $user, string $provider): ?RepositoryToken
    {
        return RepositoryToken::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->orderByDesc('updated_at')
            ->first();
    }
}
