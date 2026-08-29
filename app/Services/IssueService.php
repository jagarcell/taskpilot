<?php

namespace App\Services;

use App\Models\Agent;
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

        $issue = $this->issueRepository->create($project, $attributes);
        $this->issueRepository->recordActivity($issue, 'issue_created', $user->id, [
            'title' => $issue->title,
            'issue_key' => $issue->issue_key,
        ]);

        return $issue->fresh()->load(['labels', 'comments.user', 'activities.user']);
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

        $previousStatus = $issue->status?->value ?? $issue->status;

        $updatedIssue = $this->issueRepository->update($issue, $attributes);

        if (isset($attributes['status']) && $attributes['status'] !== null) {
            $newStatus = $attributes['status'];

            if ($previousStatus !== $newStatus) {
                $this->issueRepository->recordActivity($updatedIssue, 'status_changed', $user->id, [
                    'from' => $previousStatus,
                    'to' => $newStatus,
                ]);
            }
        }

        return $updatedIssue->fresh()->load(['labels', 'comments.user', 'activities.user']);
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

    /**
     * Load an issue with all related data needed for the project issue detail page.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @return Issue
     * Logic: enforce project access before returning the issue detail payload and eager-load project, labels, comments, and activity.
     */
    public function showIssue(Project $project, Issue $issue): Issue
    {
        $user = Auth::user();

        abort_unless($user !== null, 403);
        abort_unless($issue->project_id === $project->id, 404);
        abort_unless($this->issueRepository->userHasAccessToProject($project, $user), 403);

        return $this->issueRepository->getIssueForProject($project, $issue);
    }

    /**
     * Build the issue detail payload used by the Inertia issue detail page.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @return array<string, mixed>
     * Logic: normalize the issue, related comments, activities, workflow state, and active-agent list into the payload the frontend expects without leaving the HTTP layer to do presentation work.
     */
    public function getIssueDetailPayload(Project $project, Issue $issue): array
    {
        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'issue' => [
                'id' => $issue->id,
                'issue_key' => $issue->issue_key,
                'title' => $issue->title,
                'description' => $issue->description,
                'type' => $issue->type->value ?? $issue->type,
                'status' => $issue->status->value ?? $issue->status,
                'priority' => $issue->priority->value ?? $issue->priority,
                'reporter' => $issue->reporter ? [
                    'id' => $issue->reporter->id,
                    'name' => $issue->reporter->name,
                    'email' => $issue->reporter->email,
                ] : null,
                'assignee' => $issue->assignee ? [
                    'id' => $issue->assignee->id,
                    'name' => $issue->assignee->name,
                    'email' => $issue->assignee->email,
                ] : null,
                'labels' => $issue->labels->map(fn ($label) => [
                    'id' => $label->id,
                    'name' => $label->name,
                ])->all(),
                'comments' => $issue->comments->map(fn ($comment) => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'user_name' => $comment->user?->name,
                    'created_at' => $comment->created_at?->toDateTimeString(),
                ])->all(),
                'activities' => $issue->activities->map(fn ($activity) => [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'message' => $activity->message,
                    'user_name' => $activity->user?->name,
                    'context' => $activity->context,
                    'created_at' => $activity->created_at?->toDateTimeString(),
                ])->all(),
                'runs' => $issue->runs->map(fn ($run) => [
                    'id' => $run->id,
                    'status' => $run->status->value ?? $run->status,
                    'model' => $run->model,
                    'provider' => $run->provider,
                    'input' => $run->input,
                    'output' => $run->output,
                    'error' => $run->error,
                    'created_at' => $run->created_at?->toDateTimeString(),
                    'agent' => $run->agent ? [
                        'id' => $run->agent->id,
                        'name' => $run->agent->name,
                        'slug' => $run->agent->slug,
                    ] : null,
                    'messages' => $run->messages->map(fn ($message) => [
                        'id' => $message->id,
                        'role' => $message->role,
                        'content' => $message->content,
                        'metadata' => $message->metadata,
                        'created_at' => $message->created_at?->toDateTimeString(),
                    ])->all(),
                ])->all(),
                'workflow_runs' => $issue->workflowRuns->map(fn ($workflowRun) => [
                    'id' => $workflowRun->id,
                    'status' => $workflowRun->status,
                    'current_step' => $workflowRun->current_step,
                    'last_completed_step' => $workflowRun->metadata['last_completed_step'] ?? null,
                    'operator_action' => $workflowRun->currentOperatorAction(),
                    'can_retry' => $workflowRun->canRetry(),
                    'retry_count' => (int) ($workflowRun->metadata['retry_count'] ?? 0),
                    'created_at' => $workflowRun->created_at?->toDateTimeString(),
                ])->all(),
                'agents' => Agent::query()
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $query->whereRaw('LOWER(name) = ?', ['issue analyzer'])
                            ->orWhereRaw('LOWER(name) = ?', ['planning agent']);
                    })
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($agent) => [
                        'id' => $agent->id,
                        'name' => $agent->name,
                        'slug' => $agent->slug,
                        'model' => $agent->model,
                        'provider' => $agent->provider,
                    ])->all(),
            ],
        ];
    }
}
