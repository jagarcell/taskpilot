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

        return Inertia::render('issues/show', $this->issueService->getIssueDetailPayload($project, $issue));
    }
}
