<?php

namespace App\Repositories;

use App\Enums\AgentRunStatus;
use App\Events\AgentRunStatusChanged;
use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\AgentRun;
use App\Models\Issue;
use App\Models\User;

class AgentRunRepository
{
    /**
     * Create a new agent run for an issue.
     *
     * @param  Agent  $agent
     * @param  Issue  $issue
     * @param  User  $user
     * @param  array<string, mixed>  $attributes
     * @return AgentRun
     * Logic: persist the initial execution record with the status set to pending and the issue context attached.
     */
    public function create(Agent $agent, Issue $issue, User $user, array $attributes): AgentRun
    {
        return $agent->runs()->create([
            'issue_id' => $issue->id,
            'user_id' => $user->id,
            'model' => $attributes['model'] ?? null,
            'provider' => $attributes['provider'] ?? null,
            'status' => AgentRunStatus::PENDING,
            'input' => $attributes['input'] ?? null,
            'output' => null,
            'error' => null,
            'started_at' => now(),
        ]);
    }

    /**
     * Update the status and output payload for an agent run.
     *
     * @param  AgentRun  $agentRun
     * @param  AgentRunStatus  $status
     * @param  array<string, mixed>  $attributes
     * @return AgentRun
     * Logic: transition the run to a new lifecycle state while keeping the execution output and error metadata auditable.
     */
    public function updateStatus(AgentRun $agentRun, AgentRunStatus $status, array $attributes = []): AgentRun
    {
        $previousStatus = $agentRun->status;

        $agentRun->update([
            'status' => $status,
            'output' => $attributes['output'] ?? $agentRun->output,
            'error' => $attributes['error'] ?? $agentRun->error,
            'finished_at' => $status === AgentRunStatus::COMPLETED || $status === AgentRunStatus::FAILED ? now() : $agentRun->finished_at,
        ]);

        $updatedRun = $agentRun->fresh();

        if ($previousStatus !== $status) {
            event(new AgentRunStatusChanged($updatedRun, $previousStatus));
        }

        return $updatedRun;
    }

    /**
     * Create a persisted message entry attached to an agent run.
     *
     * @param  AgentRun  $agentRun
     * @param  string  $role
     * @param  string  $content
     * @param  array<string, mixed>  $metadata
     * @return AgentMessage
     * Logic: store a single agent message so the issue history can render the execution trail and final result.
     */
    public function createMessage(AgentRun $agentRun, string $role, string $content, array $metadata = []): AgentMessage
    {
        return $agentRun->messages()->create([
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }
}
