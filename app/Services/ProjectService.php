<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Repositories\ProjectRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

class ProjectService
{
    public function __construct(
        protected ProjectRepository $projectRepository,
    ) {}

    /**
     * Return projects visible to the authenticated user.
     *
     * @param  User  $user
     * @return Collection<int, Project>
     * Logic: delegate project listing to the repository layer and keep the controller focused on response shaping.
     */
    public function getProjectsForUser(User $user): Collection
    {
        return $this->projectRepository->listForUser($user);
    }

    /**
     * Return a project once ownership or membership access has been validated.
     *
     * @param  Project  $project
     * @param  User  $user
     * @return Project
     * Logic: enforce access control before exposing a project detail payload and load the related membership data.
     */
    public function getProjectForUser(Project $project, User $user): Project
    {
        $isOwner = $project->owner_id === $user->id;
        $isMember = $project->members()->where('user_id', $user->id)->exists();

        if (! $isOwner && ! $isMember) {
            throw new AuthorizationException('You do not have access to this project.');
        }

        return $this->projectRepository->getProjectWithRelations($project);
    }

    /**
     * Create a new project for the authenticated owner.
     *
     * @param  User  $user
     * @param  array{name: string, description?: string|null}  $attributes
     * @return Project
     * Logic: validate the owner identity and persist the project record using the repository layer.
     */
    public function createProject(User $user, array $attributes): Project
    {
        return $this->projectRepository->createForOwner($user, $attributes);
    }

    /**
     * Update a project if the user is the owner.
     *
     * @param  User  $user
     * @param  Project  $project
     * @param  array{name?: string, description?: string|null}  $attributes
     * @return Project
     * Logic: enforce ownership and delegate persistence to the repository so controller logic stays thin.
     */
    public function updateProject(User $user, Project $project, array $attributes): Project
    {
        if ($project->owner_id !== $user->id) {
            throw new AuthorizationException('Only the project owner can update this project.');
        }

        return $this->projectRepository->updateProject($project, $attributes);
    }
}
