<?php

namespace App\Services;

use App\Enums\AgentRunStatus;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Issue;
use App\Models\User;
use App\Repositories\AgentRunRepository;

class AgentRunService
{
    public function __construct(
        protected AgentRunRepository $agentRunRepository,
    ) {}

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
