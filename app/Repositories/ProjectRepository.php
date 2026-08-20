<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository
{
    /**
     * List all projects visible to a user.
     *
     * @param  User  $user
     * @return Collection<int, Project>
     * Logic: return the projects owned by the user or those where the user is a member, ordered newest first.
     */
    public function listForUser(User $user): Collection
    {
        return Project::query()
            ->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('members', fn ($memberQuery) => $memberQuery->where('user_id', $user->id));
            })
            ->latest()
            ->get();
    }

    /**
     * Load the project with the owner and member user relationships.
     *
     * @param  Project  $project
     * @return Project
     * Logic: eager-load the project owner and member user records before serializing the project detail view.
     */
    public function getProjectWithRelations(Project $project): Project
    {
        return $project->load('members.user', 'owner');
    }

    /**
     * Create a new project owned by the provided user.
     *
     * @param  User  $user
     * @param  array{owner_id?: int, name: string, description?: string|null}  $attributes
     * @return Project
     * Logic: persist the project record with the user as owner while keeping the project creation logic in the data layer.
     */
    public function createForOwner(User $user, array $attributes): Project
    {
        return $user->projects()->create($attributes);
    }

    /**
     * Update an existing project record.
     *
     * @param  Project  $project
     * @param  array{name?: string, description?: string|null}  $attributes
     * @return Project
     * Logic: persist the approved project updates and return the refreshed record for redirect or response use.
     */
    public function updateProject(Project $project, array $attributes): Project
    {
        $project->update($attributes);

        return $project->fresh();
    }
}
