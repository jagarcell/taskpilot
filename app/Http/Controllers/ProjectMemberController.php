<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectMemberRequest;
use App\Http\Requests\UpdateProjectMemberRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Services\ProjectMemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ProjectMemberController extends Controller
{
    public function __construct(
        protected ProjectMemberService $projectMemberService,
    ) {}

    /**
     * Add a user to a project as a member.
     *
     * @param  Request  $request
     * @param  Project  $project
     * @return RedirectResponse
     * Logic: validate the invite request and delegate the actual project membership logic to the service layer.
     */
    public function store(StoreProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();

        $this->projectMemberService->inviteUser(
            $project,
            Auth::user(),
            $validated['email'],
            $validated['role'] ?? 'member',
        );

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
    public function update(UpdateProjectMemberRequest $request, Project $project, ProjectMember $projectMember): RedirectResponse
    {
        $validated = $request->validated();

        $this->projectMemberService->updateMemberRole($project, $projectMember, $validated['role']);

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
        $this->projectMemberService->removeMember($project, $projectMember);

        return redirect()->route('projects.show', $project);
    }
}
