<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use App\Repositories\AgentRepository;
use App\Repositories\WorkflowDefinitionRepository;
use App\Repositories\WorkflowRunRepository;
use Illuminate\Support\Collection;

class WorkflowOrchestrationService
{
    public function __construct(
        protected AgentRunService $agentRunService,
        protected WorkflowDefinitionRepository $workflowDefinitionRepository,
        protected AgentRepository $agentRepository,
        protected WorkflowRunRepository $workflowRunRepository,
        protected ?ProjectGitHubIntegrationService $projectGitHubIntegrationService = null,
    ) {
        $this->projectGitHubIntegrationService ??= app(ProjectGitHubIntegrationService::class);
    }

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

        if (! $this->hasValidDefinition($definition)) {
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
     * Determine whether a target workflow step is eligible to run after its declared upstream dependencies complete.
     *
     * @param  WorkflowRun  $workflowRun
     * @param  WorkflowDefinition  $definition
     * @param  string  $targetStep
     * @return bool
     * Logic: prevent a stage from advancing until every configured dependency has already completed, preventing out-of-order workflow execution.
     */
    protected function hasSatisfiedDependencies(WorkflowRun $workflowRun, WorkflowDefinition $definition, string $targetStep): bool
    {
        $dependencies = $definition->config['dependencies'] ?? [];
        $requiredSteps = $dependencies[$targetStep] ?? [];

        if (empty($requiredSteps)) {
            return true;
        }

        $history = $workflowRun->metadata['execution_history'] ?? [];
        $completedSteps = [];

        foreach ($history as $event) {
            if (($event['event'] ?? null) !== 'completed') {
                continue;
            }

            $step = $event['step'] ?? null;

            if (is_string($step) && $step !== '') {
                $completedSteps[] = $step;
            }
        }

        foreach ($requiredSteps as $dependency) {
            if (! in_array((string) $dependency, $completedSteps, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate whether a workflow definition is safe to use at runtime.
     *
     * @param  WorkflowDefinition  $definition
     * @return bool
     * Logic: reject malformed workflow definitions before they can trigger invalid stage, approval, or completion behavior.
     */
    public function hasValidDefinition(WorkflowDefinition $definition): bool
    {
        $steps = $definition->steps ?? [];

        if (! is_array($steps) || empty($steps)) {
            return false;
        }

        foreach ($steps as $step) {
            if (! is_string($step) || trim($step) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Ensure a workflow definition is valid, creating a default fallback when needed.
     *
     * @param  Issue|null  $issue
     * @param  User|null  $user
     * @param  WorkflowDefinition|null  $definition
     * @return WorkflowDefinition
     * Logic: provide a safe default definition whenever the current configuration is empty or malformed, so the workflow can still start without broken stage sequencing.
     */
    public function ensureValidDefinition(?Issue $issue = null, ?User $user = null, ?WorkflowDefinition $definition = null): WorkflowDefinition
    {
        if ($definition !== null && $this->hasValidDefinition($definition)) {
            return $definition;
        }

        $fallbackDefinition = $this->workflowDefinitionRepository->findDefaultEnabled();

        if ($fallbackDefinition !== null && $this->hasValidDefinition($fallbackDefinition)) {
            return $fallbackDefinition;
        }

        return $this->workflowDefinitionRepository->createDefault();
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

        if (! $this->hasSatisfiedDependencies($workflowRun, $definition, $nextStep)) {
            $workflowRun = $this->workflowRunRepository->updateState($workflowRun, [
                'metadata' => array_merge($workflowRun->metadata ?? [], [
                    'blocked_step' => $nextStep,
                    'blocked_reason' => 'dependency_not_met',
                ]),
            ]);

            return $workflowRun;
        }

        if ($nextStep === $currentStep) {
            if ($currentStep === 'review') {
                $workflowRun = $this->finalizeReviewStage($workflowRun);
            }

            $workflowRun = $this->workflowRunRepository->updateState($workflowRun, [
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

        $workflowRun = $this->workflowRunRepository->updateState($workflowRun, [
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

        if ($nextStep === 'implementation') {
            $this->prepareImplementationBranch($workflowRun);
        }

        if ($currentStep === 'implementation') {
            $workflowRun = $this->commitImplementationArtifacts($workflowRun);
        }

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

        $workflowRun = $this->workflowRunRepository->updateState($workflowRun, [
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

        if ($nextStep === 'implementation') {
            $this->prepareImplementationBranch($workflowRun);
        }

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

        $workflowRun = $this->workflowRunRepository->updateState($workflowRun, [
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

        $workflowRun = $this->workflowRunRepository->updateState($workflowRun, [
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
    /**
     * Prepare the GitHub implementation branch as soon as the workflow reaches the implementation stage.
     *
     * @param  WorkflowRun  $workflowRun
     * @return void
     * Logic: create the project branch from the configured GitHub repository once an approved flow is ready for implementation so future commits and PRs have a consistent destination.
     */
    protected function prepareImplementationBranch(WorkflowRun $workflowRun): void
    {
        $issue = $workflowRun->issue()->first();

        if ($issue === null) {
            return;
        }

        $project = $issue->project()->first();

        if ($project === null) {
            return;
        }

        $repositoryConnection = $this->projectGitHubIntegrationService->getForProject($project);

        if ($repositoryConnection === null) {
            return;
        }

        $branchName = $this->buildImplementationBranchName($issue, $workflowRun);
        $baseBranch = $repositoryConnection->default_branch ?? 'main';
        $branchResult = $this->projectGitHubIntegrationService->createBranch($project, $branchName, $baseBranch);

        $metadata = $workflowRun->metadata ?? [];
        $metadata['github'] = [
            'repository' => $repositoryConnection->github_owner.'/'.$repositoryConnection->github_repo,
            'branch_name' => $branchResult['branch_name'] ?? $branchName,
            'base_branch' => $branchResult['base_branch'] ?? $baseBranch,
            'sha' => $branchResult['sha'] ?? null,
            'created' => (bool) ($branchResult['created'] ?? true),
        ];

        $this->workflowRunRepository->updateState($workflowRun, [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Commit and push the implementation artifact files onto the active GitHub branch before the workflow moves to QA.
     *
     * @param  WorkflowRun  $workflowRun
     * @return WorkflowRun
     * Logic: push the concrete files generated during the implementation pass into the branch created for the issue so the review stage can operate on a real diff instead of an empty branch ref.
     */
    protected function commitImplementationArtifacts(WorkflowRun $workflowRun): WorkflowRun
    {
        $issue = $workflowRun->issue()->first();

        if ($issue === null) {
            return $workflowRun;
        }

        $project = $issue->project()->first();

        if ($project === null) {
            return $workflowRun;
        }

        $repositoryConnection = $this->projectGitHubIntegrationService->getForProject($project);

        if ($repositoryConnection === null) {
            return $workflowRun;
        }

        $metadata = $workflowRun->metadata ?? [];
        $branchName = (string) (($metadata['github']['branch_name'] ?? null) ?: $this->buildImplementationBranchName($issue, $workflowRun));
        $filesChanged = $metadata['implementation']['files_changed'] ?? [];

        if (! is_array($filesChanged) || $filesChanged === []) {
            return $workflowRun;
        }

        $fileContents = [];
        $basePath = base_path();

        foreach ($filesChanged as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $absolutePath = $path;
            if (! str_starts_with($absolutePath, '/')) {
                $absolutePath = $basePath.DIRECTORY_SEPARATOR.$path;
            }

            if (! file_exists($absolutePath)) {
                continue;
            }

            $relativePath = str_replace($basePath.DIRECTORY_SEPARATOR, '', $absolutePath);
            $fileContents[$relativePath] = (string) file_get_contents($absolutePath);
        }

        if ($fileContents === []) {
            return $workflowRun;
        }

        $commitResult = $this->projectGitHubIntegrationService->commitAndPush(
            $project,
            $branchName,
            $fileContents,
            sprintf('feat: implement issue #%d changes', $issue->id),
        );

        $metadata['github'] = array_merge($metadata['github'] ?? [], [
            'last_commit_sha' => $commitResult['commit_sha'] ?? null,
            'last_commit_pushed' => (bool) ($commitResult['pushed'] ?? false),
        ]);

        $metadata['implementation'] = array_merge($metadata['implementation'] ?? [], [
            'committed' => true,
            'commit_sha' => $commitResult['commit_sha'] ?? null,
            'committed_at' => now()->toDateTimeString(),
        ]);

        return $this->workflowRunRepository->updateState($workflowRun, [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Build a stable Git branch name for the implementation stage from the issue title and identity.
     *
     * @param  Issue  $issue
     * @param  WorkflowRun  $workflowRun
     * @return string
     * Logic: keep the branch name user-friendly and deterministic so each approval-driven implementation can be tracked back to the same issue.
     */
    protected function buildImplementationBranchName(Issue $issue, WorkflowRun $workflowRun): string
    {
        $seed = trim((string) $issue->title);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($seed));
        $slug = trim((string) $slug, '-');

        if ($slug === '') {
            $slug = 'issue';
        }

        return 'feature/'.$slug.'-'.$issue->id;
    }

    /**
     * Finalize the review stage by creating the associated GitHub pull request once the implementation is ready for merge.
     *
     * @param  WorkflowRun  $workflowRun
     * @return WorkflowRun
     * Logic: keep the workflow run aligned with the repository by opening a pull request from the implementation branch once the final review gate has passed.
     */
    protected function finalizeReviewStage(WorkflowRun $workflowRun): WorkflowRun
    {
        $issue = $workflowRun->issue()->first();

        if ($issue === null) {
            return $workflowRun;
        }

        $project = $issue->project()->first();

        if ($project === null) {
            return $workflowRun;
        }

        $metadata = $workflowRun->metadata ?? [];

        if (isset($metadata['github']['pull_request'])) {
            return $workflowRun;
        }

        $repositoryConnection = $this->projectGitHubIntegrationService->getForProject($project);

        if ($repositoryConnection === null) {
            return $workflowRun;
        }

        $branchName = (string) (($metadata['github']['branch_name'] ?? null) ?: $this->buildImplementationBranchName($issue, $workflowRun));
        $baseBranch = (string) (($metadata['github']['base_branch'] ?? null) ?: ($repositoryConnection->default_branch ?? 'main'));
        $title = 'feat: '.$issue->title;
        $body = "## Summary\n\nThis change addresses issue #{$issue->id}.\n\n## Changes\n\n- Implementation work for the approved workflow\n- Related tests and validation updates\n";

        $pullRequest = $this->projectGitHubIntegrationService->createPullRequest($project, $branchName, $title, $body, $baseBranch);

        $metadata['github'] = array_merge($metadata['github'] ?? [], [
            'pull_request' => [
                'number' => (int) ($pullRequest['number'] ?? 0),
                'title' => (string) ($pullRequest['title'] ?? $title),
                'url' => (string) ($pullRequest['url'] ?? ''),
                'state' => (string) ($pullRequest['state'] ?? 'open'),
            ],
        ]);

        return $this->workflowRunRepository->updateState($workflowRun, [
            'metadata' => $metadata,
        ]);
    }

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

        $agent = $this->agentRepository->findActiveByName($agentName);

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
        $definition = $this->ensureValidDefinition($issue, $user, $definition);

        $workflowRun = $this->workflowRunRepository->createForIssue($issue, $user, $definition, [
            'current_step' => 'analysis',
            'status' => 'running',
            'metadata' => ['started_from' => 'issue_detail_page'],
        ]);

        $agent = $this->agentRepository->findActiveByName('Issue Analyzer');

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
