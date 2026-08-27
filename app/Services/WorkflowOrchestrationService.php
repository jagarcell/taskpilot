<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Issue;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use Illuminate\Support\Collection;

class WorkflowOrchestrationService
{
    public function __construct(
        protected AgentRunService $agentRunService,
    ) {}

    /**
     * Resolve the next step in a workflow definition.
     *
     * @param  WorkflowDefinition  $definition
     * @param  string|null  $currentStep
     * @return string
     * Logic: use the ordered workflow steps to determine the next step that should be launched, keeping the sequence stable and preventing out-of-order advancement.
     */
    public function resolveNextStep(WorkflowDefinition $definition, ?string $currentStep): string
    {
        $steps = $definition->steps ?? [];

        if (empty($steps)) {
            return '';
        }

        $currentIndex = $currentStep === null ? -1 : array_search($currentStep, $steps, true);

        if ($currentIndex === false) {
            return $steps[0];
        }

        $nextIndex = $currentIndex + 1;

        return $steps[$nextIndex] ?? $steps[array_key_last($steps)];
    }

    /**
     * Advance a workflow run to the next step in the configured definition.
     *
     * @param  WorkflowRun  $workflowRun
     * @param  string|null  $currentStep
     * @return WorkflowRun
     * Logic: move the run forward through the ordered workflow steps and pause for human approval when the definition requires it.
     */
    public function advanceWorkflow(WorkflowRun $workflowRun, ?string $currentStep = null): WorkflowRun
    {
        $definition = $workflowRun->workflowDefinition;
        $currentStep ??= $workflowRun->current_step;

        if ($definition === null) {
            return $workflowRun;
        }

        if ($this->hasBlockingFailure($workflowRun, $currentStep)) {
            return $workflowRun;
        }

        $nextStep = $this->resolveNextStep($definition, $currentStep);
        $requiresApproval = (bool) ($definition->config['requires_human_approval'] ?? false);

        if ($nextStep === $currentStep) {
            $workflowRun->update([
                'current_step' => $currentStep,
                'status' => 'completed',
                'metadata' => array_merge($workflowRun->metadata ?? [], [
                    'last_transition' => $currentStep,
                    'last_completed_step' => $currentStep,
                    'completed_at' => now()->toDateTimeString(),
                    'failed_step' => null,
                    'last_error' => null,
                ]),
            ]);

            $workflowRun->recordExecutionEvent($currentStep ?? 'review', 'completed', [
                'status' => 'completed',
            ]);

            return $workflowRun->fresh();
        }

        $nextStatus = $requiresApproval && $nextStep === 'approval' ? 'waiting_for_approval' : 'running';

        $workflowRun->update([
            'current_step' => $nextStep,
            'status' => $nextStatus,
            'metadata' => array_merge($workflowRun->metadata ?? [], [
                'last_transition' => $nextStep,
                'last_completed_step' => $currentStep,
                'failed_step' => null,
                'last_error' => null,
            ]),
        ]);

        $workflowRun->recordExecutionEvent($currentStep ?? 'analysis', 'completed', []);

        $this->launchStepAgent($workflowRun, $nextStep, $workflowRun->issue, $workflowRun->user);

        return $workflowRun->fresh();
    }

    /**
     * Approve the current workflow step and continue to the next stage.
     *
     * @param  WorkflowRun  $workflowRun
     * @return WorkflowRun
     * Logic: resume a paused workflow after human approval and continue the underlying step sequence.
     */
    public function approveCurrentStep(WorkflowRun $workflowRun): WorkflowRun
    {
        $definition = $workflowRun->workflowDefinition;

        if ($definition === null) {
            return $workflowRun;
        }

        if ($workflowRun->status === 'failed') {
            return $workflowRun;
        }

        $approvedStep = $workflowRun->current_step;
        $nextStep = $this->resolveNextStep($definition, $workflowRun->current_step);

        $workflowRun->update([
            'current_step' => $nextStep,
            'status' => 'running',
            'metadata' => array_merge($workflowRun->metadata ?? [], [
                'approved_at' => now()->toDateTimeString(),
                'approval_step' => $approvedStep,
                'last_completed_step' => $approvedStep,
                'failed_step' => null,
                'last_error' => null,
            ]),
        ]);

        $workflowRun->recordExecutionEvent($approvedStep ?? 'approval', 'approved', []);

        $this->launchStepAgent($workflowRun, $nextStep, $workflowRun->issue, $workflowRun->user);

        return $workflowRun->fresh();
    }

    /**
     * Mark a workflow step as failed and persist the failure metadata for retry and audit purposes.
     *
     * @param  WorkflowRun  $workflowRun
     * @param  string  $step
     * @param  array<string, mixed>  $error
     * @return WorkflowRun
     * Logic: preserve the last known failure details without advancing the workflow state, so retries can be initiated safely.
     */
    public function markFailed(WorkflowRun $workflowRun, string $step, array $error): WorkflowRun
    {
        $retryCount = (int) ($workflowRun->metadata['retry_count'] ?? 0);

        $workflowRun->update([
            'current_step' => $step,
            'status' => 'failed',
            'metadata' => array_merge($workflowRun->metadata ?? [], [
                'failed_step' => $step,
                'retry_count' => $retryCount,
                'last_error' => $error,
            ]),
        ]);

        $workflowRun->recordExecutionEvent($step, 'failed', [
            'message' => $error['message'] ?? null,
        ]);

        return $workflowRun->fresh();
    }

    /**
     * Retry the current failed workflow step and clear the failure state before re-running it.
     *
     * @param  WorkflowRun  $workflowRun
     * @return WorkflowRun
     * Logic: requeue the current step with incremented retry metadata so failed workflow stages can be retried without wiping the last error history.
     */
    public function retryCurrentStep(WorkflowRun $workflowRun): WorkflowRun
    {
        $retryCount = (int) ($workflowRun->metadata['retry_count'] ?? 0);
        $step = $workflowRun->current_step ?? 'analysis';

        $workflowRun->update([
            'current_step' => $step,
            'status' => 'running',
            'metadata' => array_merge($workflowRun->metadata ?? [], [
                'retry_count' => $retryCount + 1,
                'failed_step' => null,
                'last_error' => null,
            ]),
        ]);

        $workflowRun->recordExecutionEvent($step, 'retried', []);

        $this->launchStepAgent($workflowRun, $step, $workflowRun->issue, $workflowRun->user);

        return $workflowRun->fresh();
    }

    /**
     * Determine whether a workflow step should be blocked by a known failure in the current sequence.
     *
     * @param  WorkflowRun  $workflowRun
     * @param  string|null  $currentStep
     * @return bool
     * Logic: prevent workflow progression when the previous step has failed and no explicit retry has cleared the failure state.
     */
    protected function hasBlockingFailure(WorkflowRun $workflowRun, ?string $currentStep): bool
    {
        if ($workflowRun->status !== 'failed') {
            return false;
        }

        if ($currentStep === null) {
            return true;
        }

        return $workflowRun->metadata['failed_step'] ?? null === $currentStep;
    }

    /**
     * Launch the agent responsible for the provided workflow step when an agent exists for that stage.
     *
     * @param  WorkflowRun  $workflowRun
     * @param  string  $step
     * @param  Issue|null  $issue
     * @param  User|null  $user
     * @return void
     * Logic: map workflow steps to the active agent names and create an execution record so the sequence can continue without additional manual intervention.
     */
    protected function launchStepAgent(WorkflowRun $workflowRun, string $step, ?Issue $issue, ?User $user): void
    {
        if ($issue === null || $user === null) {
            return;
        }

        $agentName = match ($step) {
            'analysis' => 'Issue Analyzer',
            'planning' => 'Planning Agent',
            'implementation' => 'Implementation Agent',
            'testing' => 'QA Agent',
            'review' => 'Review Agent',
            default => null,
        };

        if ($agentName === null) {
            return;
        }

        $agent = Agent::query()->where('name', $agentName)->where('is_active', true)->first();

        if ($agent === null) {
            return;
        }

        $this->agentRunService->createRun($agent, $issue, $user, [
            'model' => $agent->model,
            'provider' => $agent->provider,
            'input' => ['prompt' => $issue->title.'\n\n'.$issue->description],
        ]);
    }

    /**
     * Start a workflow for an issue using the default workflow definition.
     *
     * @param  Issue  $issue
     * @param  User  $user
     * @param  WorkflowDefinition|null  $definition
     * @return WorkflowRun
     * Logic: bootstrap a workflow run for the issue and immediately launch the first step in the regression sequence: issue analysis.
     */
    public function startIssueWorkflow(Issue $issue, User $user, ?WorkflowDefinition $definition = null): WorkflowRun
    {
        $definition ??= WorkflowDefinition::query()->where('is_enabled', true)->where('config->default', true)->first();

        if ($definition === null) {
            $definition = WorkflowDefinition::factory()->create([
                'name' => 'Default issue workflow',
                'slug' => 'default-issue-workflow',
                'steps' => ['analysis', 'planning', 'approval'],
                'config' => ['default' => true],
                'is_enabled' => true,
            ]);
        }

        $workflowRun = $issue->workflowRuns()->create([
            'workflow_definition_id' => $definition->id,
            'user_id' => $user->id,
            'current_step' => 'analysis',
            'status' => 'running',
            'metadata' => ['started_from' => 'issue_detail_page'],
        ]);

        $agent = Agent::query()->where('name', 'Issue Analyzer')->where('is_active', true)->first();

        if ($agent === null) {
            return $workflowRun;
        }

        $this->agentRunService->createRun($agent, $issue, $user, [
            'model' => $agent->model,
            'provider' => $agent->provider,
            'input' => ['prompt' => $issue->title.'\n\n'.$issue->description],
        ]);

        return $workflowRun->fresh();
    }
}
