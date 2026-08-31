<?php

namespace App\Events;

use App\Models\AgentMessage;
use App\Models\AgentRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AgentRunMessageAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance.
     *
     * @param  AgentRun  $agentRun
     * @param  AgentMessage  $message
     * Logic: carry the incremental message payload so the issue detail page can append progress updates without reloading.
     */
    public function __construct(
        public AgentRun $agentRun,
        public AgentMessage $message,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     * Logic: keep progress updates scoped to the issue so only authorized project viewers see the live execution trail.
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
     * Logic: expose a stable event name for the issue page listener contract.
     */
    public function broadcastAs(): string
    {
        return 'agent-run.message-added';
    }

    /**
     * Transform the event into a payload for the frontend.
     *
     * @return array<string, mixed>
     * Logic: include the run id and the message details required to append the new content to the current agent history.
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->agentRun->id,
            'issue_id' => $this->agentRun->issue_id,
            'project_id' => $this->agentRun->issue?->project_id,
            'message' => [
                'id' => $this->message->id,
                'role' => $this->message->role,
                'content' => $this->message->content,
                'metadata' => $this->message->metadata,
                'created_at' => $this->message->created_at?->toDateTimeString(),
            ],
        ];
    }
}
