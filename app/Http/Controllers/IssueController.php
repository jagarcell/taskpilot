<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIssueRequest;
use App\Models\Project;
use App\Services\IssueService;
use Illuminate\Http\RedirectResponse;

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
}
