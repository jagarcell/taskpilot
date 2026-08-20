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
        abort_unless(
            $project->owner_id === $user->id || $project->members()->where('user_id', $user->id)->exists(),
            403,
        );

        if (isset($attributes['assignee_id']) && $attributes['assignee_id'] !== null) {
            $assigneeId = (int) $attributes['assignee_id'];
            abort_unless(
                $project->owner_id === $assigneeId || $project->members()->where('user_id', $assigneeId)->exists(),
                422,
            );
        }

        $attributes['project_id'] = $project->id;
        $attributes['reporter_id'] = $user->id;

        return $this->issueRepository->create($project, $attributes);
    }
}
