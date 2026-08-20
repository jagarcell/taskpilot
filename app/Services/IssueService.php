<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Project;
use App\Repositories\IssueRepository;
use Illuminate\Support\Facades\Auth;

class IssueService
{
    public function __construct(
        protected IssueRepository $issueRepository,
    ) {}

    /**
     * Create an issue for a project if the current user belongs to it.
     *
     * @param  Project  $project
     * @param  array<string, mixed>  $attributes
     * @return Issue
     * Logic: enforce project membership before persisting the issue and ensure the reporter is tracked.
     */
    public function createIssue(Project $project, array $attributes): Issue
    {
        $user = Auth::user();

        abort_unless($user !== null, 403);
        abort_unless($this->issueRepository->userHasAccessToProject($project, $user), 403);

        if (isset($attributes['assignee_id']) && $attributes['assignee_id'] !== null) {
            $assigneeId = (int) $attributes['assignee_id'];
            abort_unless($this->issueRepository->assigneeIsValidForProject($project, $assigneeId), 422);
        }

        $attributes['project_id'] = $project->id;
        $attributes['reporter_id'] = $user->id;

        return $this->issueRepository->create($project, $attributes);
    }

    /**
     * Update an existing issue as part of the project's issue lifecycle.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @param  array<string, mixed>  $attributes
     * @return Issue
     * Logic: enforce project access before changing issue fields and persist the update only when the issue belongs to the project.
     */
    public function updateIssue(Project $project, Issue $issue, array $attributes): Issue
    {
        $user = Auth::user();

        abort_unless($user !== null, 403);
        abort_unless($issue->project_id === $project->id, 404);
        abort_unless($this->issueRepository->userHasAccessToProject($project, $user), 403);

        if (isset($attributes['assignee_id']) && $attributes['assignee_id'] !== null) {
            $assigneeId = (int) $attributes['assignee_id'];
            abort_unless($this->issueRepository->assigneeIsValidForProject($project, $assigneeId), 422);
        }

        return $this->issueRepository->update($issue, $attributes);
    }

    /**
     * Delete an issue when the current user belongs to the project.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @return void
     * Logic: validate that the issue belongs to the project and that the user has project access before removing it.
     */
    public function deleteIssue(Project $project, Issue $issue): void
    {
        $user = Auth::user();

        abort_unless($user !== null, 403);
        abort_unless($issue->project_id === $project->id, 404);
        abort_unless($this->issueRepository->userHasAccessToProject($project, $user), 403);

        $this->issueRepository->delete($issue);
    }
}
