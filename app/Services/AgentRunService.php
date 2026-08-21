<?php

namespace App\Services;

use App\Enums\AgentRunStatus;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Repositories\AgentRunRepository;
use App\Repositories\IssueRepository;

class AgentRunService
{
    protected IssueRepository $issueRepository;

    public function __construct(
        protected AgentRunRepository $agentRunRepository,
        ?IssueRepository $issueRepository = null,
    ) {
        $this->issueRepository = $issueRepository ?? app(IssueRepository::class);
    }

    /**
     * Create a new pending agent run against an issue.
     *
     * @param  Agent  $agent
     * @param  Issue  $issue
     * @param  User  $user
     * @param  array<string, mixed>  $attributes
     * @return AgentRun
     * Logic: validate the issue context and persist a new run in the pending state so the workflow can observe it.
     */
    public function createRun(Agent $agent, Issue $issue, User $user, array $attributes): AgentRun
    {
        return $this->agentRunRepository->create($agent, $issue, $user, $attributes);
    }

    /**
     * Create a run for an issue that belongs to the current project and user access context.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @param  User  $user
     * @param  Agent  $agent
     * @param  array<string, mixed>  $attributes
     * @return AgentRun
     * Logic: enforce project membership and issue ownership before creating a queued or pending execution record.
     */
    public function createRunForIssue(Project $project, Issue $issue, User $user, Agent $agent, array $attributes): AgentRun
    {
        abort_unless($issue->project_id === $project->id, 404);
        abort_unless($this->issueRepository->userHasAccessToProject($project, $user), 403);
        abort_unless($agent->is_active, 422);

        return $this->createRun($agent, $issue, $user, $attributes);
    }

    /**
     * Mark an agent run as completed and keep the output payload.
     *
     * @param  AgentRun  $agentRun
     * @param  array<string, mixed>  $output
     * @return AgentRun
     * Logic: transition the run into the completed state while preserving whatever result the agent produced.
     */
    public function markRunAsCompleted(AgentRun $agentRun, array $output): AgentRun
    {
        return $this->agentRunRepository->updateStatus($agentRun, AgentRunStatus::COMPLETED, [
            'output' => $output,
        ]);
    }
}
