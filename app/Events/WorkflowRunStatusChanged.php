<?php

namespace App\Events;

use App\Models\WorkflowRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowRunStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance.
     *
     * @param  WorkflowRun  $updatedRun
     * @param  string|null  $previousStatus
     * @param  string|null  $previousStep
     * Logic: carry the workflow transition payload so the issue detail page can update workflow state without forcing a full reload.
     */
    public function __construct(
        public WorkflowRun $updatedRun,
        public ?string $previousStatus = null,
        public ?string $previousStep = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     * Logic: keep workflow updates scoped to the issue project so only authorized users receive state transitions.
     */
    public function broadcastOn(): array
    {
        $projectId = $this->updatedRun->issue?->project_id;
        $issueId = $this->updatedRun->issue_id;

        if ($projectId === null || $issueId === null) {
            return [];
        }

        return [
            new PrivateChannel("project.{$projectId}.issue.{$issueId}.workflow-runs"),
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     * Logic: expose a stable contract for the workflow listener so the issue page can react to status changes.
     */
    public function broadcastAs(): string
    {
        return 'workflow-run.status-changed';
    }

    /**
     * Transform the event into a minimal payload for the frontend.
     *
     * @return array<string, mixed>
     * Logic: include only the fields required for the workflow panel to update in place without re-fetching the page.
     */
    public function broadcastWith(): array
    {
        return [
            'workflow_run_id' => $this->updatedRun->id,
            'issue_id' => $this->updatedRun->issue_id,
            'project_id' => $this->updatedRun->issue?->project_id,
            'status' => $this->updatedRun->status,
            'current_step' => $this->updatedRun->current_step,
            'last_completed_step' => $this->updatedRun->metadata['last_completed_step'] ?? null,
            'failed_step' => $this->updatedRun->metadata['failed_step'] ?? null,
            'last_error' => $this->updatedRun->metadata['last_error'] ?? null,
            'operator_action' => $this->updatedRun->currentOperatorAction(),
            'can_retry' => $this->updatedRun->canRetry(),
            'retry_count' => (int) ($this->updatedRun->metadata['retry_count'] ?? 0),
            'previous_status' => $this->previousStatus,
            'previous_step' => $this->previousStep,
        ];
    }
}
