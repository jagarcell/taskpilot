<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Notifications\MemberInvited;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectMemberController extends Controller
{
    /**
     * Add a user to a project as a member.
     *
     * @param  Request  $request
     * @param  Project  $project
     * @return RedirectResponse
     * Logic: allow the project owner to invite a user by email and store the membership.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->owner_id === Auth::id(), 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['nullable', 'string', 'in:member,owner'],
        ]);

        $user = User::query()->where('email', $validated['email'])->firstOrFail();

        $project->members()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => $validated['role'] ?? 'member'],
        );

        // Notify the invited user via email
        try {
            $user->notify(new MemberInvited($project, Auth::user()));
        } catch (\Throwable $e) {
            // Fail silently to avoid blocking the invite flow; logging handled elsewhere
        }

        return redirect()->route('projects.index');
    }

    /**
     * Update a member's access role within a project.
     *
     * @param  Request  $request
     * @param  Project  $project
     * @param  ProjectMember  $projectMember
     * @return RedirectResponse
     * Logic: permit the owner to adjust a member's role and keep membership data aligned with the project's admin model.
     */
    public function update(Request $request, Project $project, ProjectMember $projectMember): RedirectResponse
    {
        abort_unless($project->owner_id === Auth::id(), 403);
        abort_unless($projectMember->project_id === $project->id, 404);
        abort_if($projectMember->user_id === $project->owner_id, 403);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:member,owner'],
        ]);

        if ($validated['role'] === 'owner') {
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

            return redirect()->route('projects.show', $project);
        }

        $projectMember->update([
            'role' => $validated['role'],
        ]);

        return redirect()->route('projects.show', $project);
    }

    /**
     * Remove a member from the project.
     *
     * @param  Project  $project
     * @param  ProjectMember  $projectMember
     * @return RedirectResponse
     * Logic: allow the project owner to revoke access for a specific member while preserving the project's ownership model.
     */
    public function destroy(Project $project, ProjectMember $projectMember): RedirectResponse
    {
        abort_unless($project->owner_id === Auth::id(), 403);
        abort_unless($projectMember->project_id === $project->id, 404);
        abort_if($projectMember->user_id === $project->owner_id, 403);

        $projectMember->delete();

        return redirect()->route('projects.show', $project);
    }
}
