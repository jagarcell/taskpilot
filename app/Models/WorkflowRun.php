<?php

namespace App\Models;

use Database\Factories\WorkflowRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRun extends Model
{
    /** @use HasFactory<WorkflowRunFactory> */
    use HasFactory;

    protected $table = 'workflow_runs';

    protected $primaryKey = 'id';

    protected $fillable = [
        'workflow_definition_id',
        'issue_id',
        'user_id',
        'current_step',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Record a workflow-stage event in the execution history for auditability.
     *
     * @param  string  $step
     * @param  string  $event
     * @param  array<string, mixed>  $payload
     * @return void
     * Logic: append a structured, timestamped event to the run metadata so each stage transition, failure, approval, and retry can be inspected later.
     */
    public function recordExecutionEvent(string $step, string $event, array $payload = []): void
    {
        $history = $this->metadata['execution_history'] ?? [];

        $entry = array_merge([
            'step' => $step,
            'event' => $event,
            'status' => $this->status,
            'timestamp' => now()->toDateTimeString(),
        ], $payload);

        $history[] = $entry;

        $this->metadata = array_merge($this->metadata ?? [], [
            'execution_history' => $history,
        ]);

        $this->save();
    }

    /**
     * Determine whether the workflow is paused for human approval.
     *
     * @return bool
     * Logic: provide a clear, reusable state check so the workflow UI and orchestration services can identify approval-gated steps without duplicating status comparisons.
     */
    public function isWaitingForApproval(): bool
    {
        return $this->status === 'waiting_for_approval';
    }

    /**
     * Determine whether the run is in a retryable failed state.
     *
     * @return bool
     * Logic: identify safe retry candidates while preventing attempts on non-failed or approval-blocked workflow states.
     */
    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->current_step !== null;
    }

    /**
     * Get the next operator action that should be taken for the current workflow state.
     *
     * @return string|null
     * Logic: derive a single action contract for the UI so approval and retry states can be surfaced and acted on consistently.
     */
    public function currentOperatorAction(): ?string
    {
        if ($this->isWaitingForApproval()) {
            return 'approve';
        }

        if ($this->canRetry()) {
            return 'retry';
        }

        return null;
    }

    /**
     * Determine whether the workflow has reached a terminal completed state.
     *
     * @return bool
     * Logic: allow downstream code to distinguish between active workflow states and a finished run without relying on raw status strings.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Build a compact workflow summary for issue-level status rendering.
     *
     * @return array<string, mixed>
     * Logic: provide a stable, UI-friendly snapshot of the current workflow state so issue views can render status, operator actions, and retry information without parsing raw metadata directly.
     */
    public function summary(): array
    {
        return [
            'status' => $this->status,
            'current_step' => $this->current_step,
            'last_completed_step' => $this->metadata['last_completed_step'] ?? null,
            'operator_action' => $this->currentOperatorAction(),
            'is_completed' => $this->isCompleted(),
            'can_retry' => $this->canRetry(),
            'last_error' => $this->metadata['last_error'] ?? null,
            'retry_count' => (int) ($this->metadata['retry_count'] ?? 0),
        ];
    }

    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
