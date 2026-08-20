<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

class ProjectMemberRepository
{
    /**
     * Find a user by their email address.
     *
     * @param  string  $email
     * @return User
     * Logic: resolve the invited user record from the email address so the project membership can be created against the correct account.
     */
    public function findUserByEmail(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    /**
     * Create or return the membership for a project and user.
     *
     * @param  Project  $project
     * @param  User  $user
     * @param  string  $role
     * @return ProjectMember
     * Logic: ensure the user has a single membership record for the project and persist the requested role.
     */
    public function addMember(Project $project, User $user, string $role): ProjectMember
    {
        return $project->members()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => $role],
        );
    }

    /**
     * Update a project's member role.
     *
     * @param  ProjectMember  $projectMember
     * @param  string  $role
     * @return ProjectMember
     * Logic: persist the role change for the member record so the project's access model stays in sync.
     */
    public function updateRole(ProjectMember $projectMember, string $role): ProjectMember
    {
        $projectMember->update([
            'role' => $role,
        ]);

        return $projectMember->fresh();
    }

    /**
     * Remove a member from the project.
     *
     * @param  ProjectMember  $projectMember
     * @return void
     * Logic: delete the membership record once the project owner confirms the member should be revoked.
     */
    public function deleteMember(ProjectMember $projectMember): void
    {
        $projectMember->delete();
    }
}
