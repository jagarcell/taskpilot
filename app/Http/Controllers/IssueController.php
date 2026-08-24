<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Models\Agent;
use App\Models\Issue;
use App\Models\Project;
use App\Services\IssueService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function __construct(
        protected IssueService $issueService,
    ) {}

    /**
     * Store a newly created issue for an existing project.
     *
     * @param  StoreIssueRequest  $request
     * @param  Project  $project
     * @return RedirectResponse
     * Logic: validate the issue payload and delegate creation to the service layer, which enforces project membership.
     */
    public function store(StoreIssueRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();

        $this->issueService->createIssue($project, $validated);

        return redirect()->route('projects.show', $project);
    }

    /**
     * Update an existing issue belonging to a project.
     *
     * @param  UpdateIssueRequest  $request
     * @param  Project  $project
     * @param  Issue  $issue
     * @return RedirectResponse
     * Logic: validate the fields and update the issue only when the current user has access to the project.
     */
    public function update(UpdateIssueRequest $request, Project $project, Issue $issue): RedirectResponse
    {
        $validated = $request->validated();

        $this->issueService->updateIssue($project, $issue, $validated);

        return redirect()->route('projects.show', $project);
    }

    /**
     * Delete an issue attached to a project.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @return RedirectResponse
     * Logic: authorize the user against the project and remove the issue only when it belongs to that project.
     */
    public function destroy(Project $project, Issue $issue): RedirectResponse
    {
        $this->issueService->deleteIssue($project, $issue);

        return redirect()->route('projects.show', $project);
    }

    /**
     * Display the issue detail view for a project issue.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @return Response
     * Logic: authorize access against the project first, then render the issue data and its activity feed in the detail page.
     */
    public function show(Project $project, Issue $issue): Response
    {
        $issue = $this->issueService->showIssue($project, $issue);

        return Inertia::render('issues/show', [
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
                    'created_at' => $run->created_at?->toDateTimeString(),
                    'agent' => $run->agent ? [
                        'id' => $run->agent->id,
                        'name' => $run->agent->name,
                        'slug' => $run->agent->slug,
                    ] : null,
                ])->all(),
                'agents' => Agent::query()
                    ->where('is_active', true)
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
        ]);
    }
}
