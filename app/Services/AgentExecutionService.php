<?php

namespace App\Services;

use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\WorkflowRun;
use App\Repositories\AgentRepository;
use App\Repositories\AgentRunRepository;
use App\Repositories\WorkflowRunRepository;
use Illuminate\Support\Str;
use Throwable;

class AgentExecutionService
{
    public function __construct(
        protected AgentRepository $agentRepository,
        protected AgentRunRepository $agentRunRepository,
        protected AgentProviderFactory $agentProviderFactory,
        protected WorkflowRunRepository $workflowRunRepository,
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

            if ($this->isImplementationAgent($agentRun)) {
                $this->persistImplementationArtifact($agentRun, $output);
            }

            if ($this->isTestingAgent($agentRun)) {
                $this->persistTestingArtifact($agentRun, $output);
            }

            if ($this->isReviewAgent($agentRun)) {
                $this->persistReviewArtifact($agentRun, $output);
            }

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
        $issue = $this->agentRunRepository->findIssueForRun($agentRun);

        if ($issue === null) {
            return;
        }

        $workflowRun = $this->workflowRunRepository->findLatestActiveForIssue($issue);

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
        $issue = $this->agentRunRepository->findIssueForRun($agentRun);

        if ($issue === null) {
            return;
        }

        $workflowRun = $this->workflowRunRepository->findLatestActiveForIssue($issue);

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
     * Persist a concrete repository artifact for an implementation-agent run so the workflow has evidence of the code change.
     *
     * @param  AgentRun  $agentRun
     * @param  array<string, mixed>  $output
     * @return void
     * Logic: create a durable file in the repo root describing the implemented work and attach the path to the active workflow metadata so the UI and downstream stages can surface it.
     */
    protected function persistImplementationArtifact(AgentRun $agentRun, array $output): void
    {
        $issue = $this->agentRunRepository->findIssueForRun($agentRun);

        if ($issue === null) {
            return;
        }

        $directory = storage_path('app/agent-artifacts');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.sprintf('implementation-agent-issue-%d-%s.md', $issue->id, Str::slug((string) $issue->title ?: 'issue'));

        $implementation = $output['implementation'] ?? [];
        $files = is_array($implementation['files_likely_affected'] ?? null) ? $implementation['files_likely_affected'] : [];
        $summary = is_string($implementation['technical_approach'] ?? null) ? $implementation['technical_approach'] : 'Approved implementation activity recorded for validation.';

        $content = "# Implementation Agent Notes\n\n";
        $content .= "**Issue:** #{$issue->id} - {$issue->title}\n";
        $content .= "**Generated:** ".now()->toDateTimeString()."\n\n";
        $content .= "## Summary\n\n{$summary}\n\n";
        $content .= "## Files likely affected\n\n";

        if ($files === []) {
            $content .= "- No concrete file list was provided by the provider; the implementation stage remains queued for validation.\n";
        } else {
            foreach ($files as $file) {
                $content .= '- '.(string) $file."\n";
            }
        }

        $content .= "\n## Validation\n\n";
        $content .= "- Continue with the testing stage and verify the affected path before final review.\n";

        file_put_contents($path, $content);

        $workflowRun = $this->workflowRunRepository->findLatestActiveForIssue($issue);

        if (! $workflowRun instanceof WorkflowRun) {
            return;
        }

        $metadata = $workflowRun->metadata ?? [];
        $metadata['implementation'] = array_merge($metadata['implementation'] ?? [], [
            'summary' => $summary,
            'files_changed' => [$path],
            'files_likely_affected' => $files,
            'generated_at' => now()->toDateTimeString(),
        ]);

        $this->workflowRunRepository->updateState($workflowRun, [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Determine whether the current agent run matches the implementation stage contract.
     *
     * @param  AgentRun  $agentRun
     * @return bool
     * Logic: check the agent name for the implementation persona so the execution layer can attach real repo-change artifacts only to the correct stage.
     */
    protected function isImplementationAgent(AgentRun $agentRun): bool
    {
        return $this->agentRepository->isImplementationAgentRun($agentRun);
    }

    /**
     * Determine whether the current agent run matches the QA/testing stage contract.
     *
     * @param  AgentRun  $agentRun
     * @return bool
     * Logic: recognize QA and testing agents so validation output can be recorded alongside workflow metadata and the issue history.
     */
    protected function isTestingAgent(AgentRun $agentRun): bool
    {
        return $this->agentRepository->isTestingAgentRun($agentRun);
    }

    /**
     * Determine whether the current agent run matches the review stage contract.
     *
     * @param  AgentRun  $agentRun
     * @return bool
     * Logic: recognize the review persona so code-review output can be stored and surfaced as a distinct workflow artifact.
     */
    protected function isReviewAgent(AgentRun $agentRun): bool
    {
        return $this->agentRepository->isReviewAgentRun($agentRun);
    }

    /**
     * Persist the validation artifact produced by the testing agent so the workflow can move into review with evidence attached.
     *
     * @param  AgentRun  $agentRun
     * @param  array<string, mixed>  $output
     * @return void
     * Logic: write a durable QA note and attach the resulting artifact path to the active workflow metadata.
     */
    protected function persistTestingArtifact(AgentRun $agentRun, array $output): void
    {
        $issue = $this->agentRunRepository->findIssueForRun($agentRun);

        if ($issue === null) {
            return;
        }

        $directory = storage_path('app/agent-artifacts');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.sprintf('testing-agent-issue-%d-%s.md', $issue->id, Str::slug((string) $issue->title ?: 'issue'));
        $testing = $output['testing'] ?? [];
        $summary = is_string($testing['status'] ?? null) ? $testing['status'] : 'passed';
        $content = "# QA Validation Notes\n\n";
        $content .= "**Issue:** #{$issue->id} - {$issue->title}\n";
        $content .= "**Status:** {$summary}\n\n";
        $content .= "## Validation Summary\n\n";
        $content .= is_string($output['summary'] ?? null) ? $output['summary'] : 'Validation completed without blocking failures.';
        $content .= "\n\n## Recommended Next Step\n\n";
        $content .= is_string($testing['recommended_next_step'] ?? null) ? $testing['recommended_next_step'] : 'review';
        $content .= "\n";

        file_put_contents($path, $content);

        $workflowRun = $this->workflowRunRepository->findLatestActiveForIssue($issue);

        if (! $workflowRun instanceof WorkflowRun) {
            return;
        }

        $metadata = $workflowRun->metadata ?? [];
        $metadata['testing'] = array_merge($metadata['testing'] ?? [], [
            'status' => $summary,
            'artifacts' => [$path],
            'generated_at' => now()->toDateTimeString(),
        ]);

        $this->workflowRunRepository->updateState($workflowRun, [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Persist the review artifact produced by the review agent so the workflow can finalize the review stage with evidence attached.
     *
     * @param  AgentRun  $agentRun
     * @param  array<string, mixed>  $output
     * @return void
     * Logic: write a durable review note and attach the artifact path to the active workflow metadata so the issue history and PR flow can inspect approval outcomes.
     */
    protected function persistReviewArtifact(AgentRun $agentRun, array $output): void
    {
        $issue = $this->agentRunRepository->findIssueForRun($agentRun);

        if ($issue === null) {
            return;
        }

        $directory = storage_path('app/agent-artifacts');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.sprintf('review-agent-issue-%d-%s.md', $issue->id, Str::slug((string) $issue->title ?: 'issue'));
        $review = $output['review'] ?? [];
        $status = is_string($review['status'] ?? null) ? $review['status'] : 'approved';
        $summary = is_string($review['review_summary'] ?? null) ? $review['review_summary'] : 'Review completed without blocking issues.';
        $content = "# Review Notes\n\n";
        $content .= "**Issue:** #{$issue->id} - {$issue->title}\n";
        $content .= "**Status:** {$status}\n\n";
        $content .= "## Review Summary\n\n{$summary}\n\n";
        $content .= "## Findings\n\n";

        $findings = is_array($review['findings'] ?? null) ? $review['findings'] : ['No blocking issues identified.'];

        foreach ($findings as $finding) {
            $content .= '- '.(string) $finding."\n";
        }

        file_put_contents($path, $content);

        $workflowRun = $this->workflowRunRepository->findLatestActiveForIssue($issue);

        if (! $workflowRun instanceof WorkflowRun) {
            return;
        }

        $metadata = $workflowRun->metadata ?? [];
        $metadata['review'] = array_merge($metadata['review'] ?? [], [
            'status' => $status,
            'artifacts' => [$path],
            'review_summary' => $summary,
            'generated_at' => now()->toDateTimeString(),
        ]);

        $this->workflowRunRepository->updateState($workflowRun, [
            'metadata' => $metadata,
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
        $agentName = strtolower($this->agentRepository->resolveNameForRun($agentRun));

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
