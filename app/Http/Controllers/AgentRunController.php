<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgentRunRequest;
use App\Models\Agent;
use App\Models\Issue;
use App\Models\Project;
use App\Services\AgentRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AgentRunController extends Controller
{
    public function __construct(
        protected AgentRunService $agentRunService,
    ) {}

    /**
     * Store a new agent run for an issue.
     *
     * @param  StoreAgentRunRequest  $request
     * @param  Project  $project
     * @param  Issue  $issue
     * @return RedirectResponse
     * Logic: validate the agent request and create the run only for active agents attached to issues the current user can access.
     */
    public function store(StoreAgentRunRequest $request, Project $project, Issue $issue): RedirectResponse
    {
        $validated = $request->validated();
        $agent = Agent::query()->findOrFail($validated['agent_id']);

        $this->agentRunService->createRunForIssue(
            $project,
            $issue,
            Auth::user(),
            $agent,
            [
                'model' => $validated['model'] ?? $agent->model,
                'provider' => $validated['provider'] ?? $agent->provider,
                'input' => $validated['input'] ?? [],
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agent run queued successfully.')]);

        return redirect()->route('projects.issues.show', [$project, $issue]);
    }
}
