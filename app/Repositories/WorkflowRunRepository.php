<?php

namespace App\Repositories;

use App\Events\WorkflowRunStatusChanged;
use App\Models\Issue;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;

class WorkflowRunRepository
{
    /**
     * Create a new workflow run for an issue.
     *
     * @param  Issue  $issue
     * @param  User  $user
     * @param  WorkflowDefinition  $definition
     * @param  array<string, mixed>  $attributes
     * @return WorkflowRun
     * Logic: persist the issue workflow run with the configured definition and initial status so orchestration can advance it safely.
     */
    public function createForIssue(Issue $issue, User $user, WorkflowDefinition $definition, array $attributes = []): WorkflowRun
    {
        $workflowRun = $issue->workflowRuns()->create([
            'workflow_definition_id' => $definition->id,
            'user_id' => $user->id,
            'current_step' => $attributes['current_step'] ?? 'analysis',
            'status' => $attributes['status'] ?? 'running',
            'metadata' => $attributes['metadata'] ?? ['started_from' => 'issue_detail_page'],
        ]);

        $freshRun = $workflowRun->fresh();

        event(new WorkflowRunStatusChanged(
            updatedRun: $freshRun,
            previousStatus: null,
            previousStep: null,
        ));

        return $freshRun;
    }

    /**
     * Persist a workflow state update in a single repository method.
     *
     * @param  WorkflowRun  $workflowRun
     * @param  array<string, mixed>  $attributes
     * @return WorkflowRun
     * Logic: apply the next step, status, and metadata changes while preserving the existing payload and refreshing the model for the caller.
     */
    public function updateState(WorkflowRun $workflowRun, array $attributes): WorkflowRun
    {
        $previousStatus = $workflowRun->status;
        $previousStep = $workflowRun->current_step;

        $workflowRun->update($attributes);
        $updatedRun = $workflowRun->fresh();

        if (($attributes['status'] ?? null) !== null || array_key_exists('current_step', $attributes) || array_key_exists('metadata', $attributes)) {
            event(new WorkflowRunStatusChanged(
                updatedRun: $updatedRun,
                previousStatus: $previousStatus,
                previousStep: $previousStep,
            ));
        }

        return $updatedRun;
    }
}
