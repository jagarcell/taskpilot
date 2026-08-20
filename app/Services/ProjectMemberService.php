<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Notifications\MemberInvited;
use App\Repositories\ProjectMemberRepository;
use Illuminate\Support\Facades\Log;

class ProjectMemberService
{
    public function __construct(
        protected ProjectMemberRepository $projectMemberRepository,
    ) {}

    /**
     * Invite a user to a project by email address.
     *
     * @param  Project  $project
     * @param  User  $inviter
     * @param  string  $email
     * @param  string  $role
     * @return ProjectMember
     * Logic: enforce project ownership before creating the member record and notify the invited user.
     */
    public function inviteUser(Project $project, User $inviter, string $email, string $role = 'member'): ProjectMember
    {
        if ($project->owner_id !== $inviter->id) {
            abort(403);
        }

        $user = $this->projectMemberRepository->findUserByEmail($email);
        $membership = $this->projectMemberRepository->addMember($project, $user, $role);

        try {
            $user->notify(new MemberInvited($project, $inviter));
        } catch (\Throwable $e) {
            Log::warning('Project member invitation notification failed.', [
                'project_id' => $project->id,
                'user_id' => $user->id,
                'inviter_id' => $inviter->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return $membership;
    }

    /**
     * Update a project member's role.
     *
     * @param  Project  $project
     * @param  ProjectMember  $projectMember
     * @param  string  $role
     * @return ProjectMember
     * Logic: ensure only the project owner can change member access, protect the current owner, and persist role changes.
     */
    public function updateMemberRole(Project $project, ProjectMember $projectMember, string $role): ProjectMember
    {
        abort_unless($project->owner_id === auth()->id(), 403);
        abort_unless($projectMember->project_id === $project->id, 404);
        abort_if($projectMember->user_id === $project->owner_id, 403);

        if ($role === 'owner') {
            $previousOwnerId = $project->owner_id;
            $newOwnerId = $projectMember->user_id;

            $project->owner_id = $newOwnerId;
            $project->save();

            $project->members()
                ->updateOrCreate(
                    ['user_id' => $previousOwnerId],
                    ['role' => 'member'],
                );

            $projectMember->update([
                'role' => 'owner',
            ]);

            return $projectMember->fresh();
        }

        return $this->projectMemberRepository->updateRole($projectMember, $role);
    }

    /**
     * Remove a member from a project.
     *
     * @param  Project  $project
     * @param  ProjectMember  $projectMember
     * @return void
     * Logic: enforce ownership restrictions and delete the membership record while preserving the existing owner protections.
     */
    public function removeMember(Project $project, ProjectMember $projectMember): void
    {
        abort_unless($project->owner_id === auth()->id(), 403);
        abort_unless($projectMember->project_id === $project->id, 404);
        abort_if($projectMember->user_id === $project->owner_id, 403);

        $this->projectMemberRepository->deleteMember($projectMember);
    }
}
