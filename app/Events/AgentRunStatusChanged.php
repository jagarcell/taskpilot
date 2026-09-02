<?php

namespace App\Events;

use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AgentRunStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance.
     *
     * @param  AgentRun  $agentRun
     * @param  AgentRunStatus  $previousStatus
     * Logic: carry the run and status transition so listeners can update the issue detail UI in real time without refresh.
     */
    public function __construct(
        public AgentRun $agentRun,
        public AgentRunStatus $previousStatus,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     * Logic: scope realtime updates to the project and issue so only authorized viewers receive agent-monitoring events.
     */
    public function broadcastOn(): array
    {
        $projectId = $this->agentRun->issue?->project_id;
        $issueId = $this->agentRun->issue_id;

        if ($projectId === null || $issueId === null) {
            return [];
        }

        return [
            new PrivateChannel("project.{$projectId}.issue.{$issueId}.agent-runs"),
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     * Logic: expose a clear public event name for the frontend listener contract.
     */
    public function broadcastAs(): string
    {
        return 'agent-run.status-changed';
    }

    /**
     * Transform the event into a minimal payload for the frontend.
     *
     * @return array<string, mixed>
     * Logic: include the smallest payload needed to update a run row in place.
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->agentRun->id,
            'issue_id' => $this->agentRun->issue_id,
            'project_id' => $this->agentRun->issue?->project_id,
            'status' => $this->agentRun->status,
            'previous_status' => $this->previousStatus,
            'finished_at' => $this->agentRun->finished_at?->toISOString(),
        ];
    }
}
