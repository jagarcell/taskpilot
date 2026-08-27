<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Project;
use App\Models\WorkflowRun;
use App\Services\WorkflowOrchestrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class WorkflowRunController extends Controller
{
    public function __construct(
        protected WorkflowOrchestrationService $workflowOrchestrationService,
    ) {}

    /**
     * Start a workflow for an issue using the active default definition.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @return RedirectResponse
     * Logic: create the first workflow run for the issue and immediately launch the issue-analysis stage from the issue detail page.
     */
    public function start(Project $project, Issue $issue): RedirectResponse
    {
        abort_unless($issue->project_id === $project->id, 404);

        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless($project->owner_id === $user->id || $project->members()->where('user_id', $user->id)->exists(), 403);

        $this->workflowOrchestrationService->startIssueWorkflow(
            $issue,
            $user,
        );

        return redirect()->route('projects.issues.show', [$project, $issue]);
    }

    /**
     * Approve the current workflow step for an issue workflow run.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @param  WorkflowRun  $workflowRun
     * @return RedirectResponse
     * Logic: authorize access to the project and continue the workflow only when the current run is waiting for approval.
     */
    public function approve(Project $project, Issue $issue, WorkflowRun $workflowRun): RedirectResponse
    {
        abort_unless($workflowRun->issue_id === $issue->id, 404);
        abort_unless($issue->project_id === $project->id, 404);

        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless($project->owner_id === $user->id || $project->members()->where('user_id', $user->id)->exists(), 403);

        $this->workflowOrchestrationService->approveCurrentStep($workflowRun);

        return redirect()->route('projects.issues.show', [$project, $issue]);
    }

    /**
     * Retry the current failed workflow step for an issue workflow run.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @param  WorkflowRun  $workflowRun
     * @return RedirectResponse
     * Logic: allow the workflow to resume from a failed state when the run is marked retryable and the issue belongs to the current project.
     */
    public function retry(Project $project, Issue $issue, WorkflowRun $workflowRun): RedirectResponse
    {
        abort_unless($workflowRun->issue_id === $issue->id, 404);
        abort_unless($issue->project_id === $project->id, 404);

        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless($project->owner_id === $user->id || $project->members()->where('user_id', $user->id)->exists(), 403);

        $this->workflowOrchestrationService->retryCurrentStep($workflowRun);

        return redirect()->route('projects.issues.show', [$project, $issue]);
    }
}
