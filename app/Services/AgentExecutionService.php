<?php

namespace App\Services;

use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\WorkflowRun;
use App\Repositories\AgentRunRepository;
use Throwable;

class AgentExecutionService
{
    public function __construct(
        protected AgentRunRepository $agentRunRepository,
        protected AgentProviderFactory $agentProviderFactory,
        protected ?WorkflowOrchestrationService $workflowOrchestrationService = null,
    ) {
        $this->workflowOrchestrationService ??= app(WorkflowOrchestrationService::class);
    }

    /**
     * Execute an agent run through the configured provider and persist the result.
     *
     * @param  AgentRun  $agentRun
     * @return AgentRun
     * Logic: transition the run to running, invoke the provider abstraction, and record either completion or failure.
     */
    public function execute(AgentRun $agentRun): AgentRun
    {
        $this->agentRunRepository->updateStatus($agentRun, AgentRunStatus::RUNNING);

        try {
            if ($this->shouldForceWorkflowFailure()) {
                throw new \RuntimeException('QA forced workflow failure for retry testing.');
            }

            $provider = $this->agentProviderFactory->resolve($agentRun->provider);
            $output = $provider->execute($agentRun);
            $completedRun = $this->agentRunRepository->updateStatus($agentRun, AgentRunStatus::COMPLETED, [
                'output' => $output,
            ]);

            $summary = is_array($output) && isset($output['summary']) ? (string) $output['summary'] : 'Agent execution completed.';
            $this->agentRunRepository->createMessage($completedRun, 'assistant', $summary, ['output' => $output]);

            $this->advanceWorkflowForCompletedRun($completedRun);

            return $completedRun;
        } catch (Throwable $exception) {
            return $this->handleJobFailure($agentRun, $exception);
        }
    }

    /**
     * Persist the failure details for a crashed or rejected queued agent run and mark the issue workflow retryable.
     *
     * @param  AgentRun  $agentRun
     * @param  Throwable  $exception
     * @return AgentRun
     * Logic: keep the run and workflow in a failed state with diagnostic data so the UI can surface retry actions when a queue job crashes.
     */
    public function handleJobFailure(AgentRun $agentRun, Throwable $exception): AgentRun
    {
        $failedRun = $this->agentRunRepository->updateStatus($agentRun, AgentRunStatus::FAILED, [
            'error' => [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ],
        ]);

        $this->agentRunRepository->createMessage($failedRun, 'system', $exception->getMessage(), [
            'error' => [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ],
        ]);

        $this->failWorkflowForAgentRun($failedRun, $exception);

        return $failedRun;
    }

    /**
     * Determine whether QA mode should intentionally force a workflow failure for retry testing.
     *
     * @return bool
     * Logic: allow local or QA environments to simulate a failed workflow stage without changing production defaults.
     */
    public function shouldForceWorkflowFailure(): bool
    {
        if (app()->environment(['testing', 'production'])) {
            return false;
        }

        return filter_var(env('WORKFLOW_FORCE_FAILURE', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Advance the active workflow when the completed agent run corresponds to the current workflow stage.
     *
     * @param  AgentRun  $agentRun
     * @return void
     * Logic: map the agent name back to the current workflow step and continue the active issue workflow once an agent finishes successfully.
     */
    protected function advanceWorkflowForCompletedRun(AgentRun $agentRun): void
    {
        $issue = $agentRun->issue()->first();

        if ($issue === null) {
            return;
        }

        $workflowRun = $issue->workflowRuns()
            ->whereIn('status', ['running', 'waiting_for_approval', 'failed'])
            ->orderByDesc('created_at')
            ->first();

        if (! $workflowRun instanceof WorkflowRun) {
            return;
        }

        $step = $this->resolveWorkflowStepForAgent($agentRun);

        if ($step === null || $workflowRun->current_step !== $step) {
            return;
        }

        $this->workflowOrchestrationService->advanceWorkflow($workflowRun, $step);
    }

    /**
     * Mark the active workflow as failed when the agent run fails on the current workflow stage.
     *
     * @param  AgentRun  $agentRun
     * @param  Throwable  $exception
     * @return void
     * Logic: preserve the failure context and stop the workflow progression until the run is retried or corrected.
     */
    protected function failWorkflowForAgentRun(AgentRun $agentRun, Throwable $exception): void
    {
        $issue = $agentRun->issue()->first();

        if ($issue === null) {
            return;
        }

        $workflowRun = $issue->workflowRuns()
            ->whereIn('status', ['running', 'waiting_for_approval', 'failed'])
            ->orderByDesc('created_at')
            ->first();

        if (! $workflowRun instanceof WorkflowRun) {
            return;
        }

        $step = $this->resolveWorkflowStepForAgent($agentRun);

        if ($step === null || $workflowRun->current_step !== $step) {
            return;
        }

        $this->workflowOrchestrationService->markFailed($workflowRun, $step, [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Resolve the workflow step that corresponds to a completed agent.
     *
     * @param  AgentRun  $agentRun
     * @return string|null
     * Logic: map agent names back to workflow stages so an agent completion can trigger the correct next step in the issue workflow.
     */
    protected function resolveWorkflowStepForAgent(AgentRun $agentRun): ?string
    {
        $agentName = strtolower((string) ($agentRun->agent()->first()?->name ?? ''));

        return match (true) {
            str_contains($agentName, 'issue analyzer') => 'analysis',
            str_contains($agentName, 'planning') => 'planning',
            str_contains($agentName, 'implementation') => 'implementation',
            str_contains($agentName, 'qa') || str_contains($agentName, 'testing') => 'testing',
            str_contains($agentName, 'review') => 'review',
            default => null,
        };
    }
}
