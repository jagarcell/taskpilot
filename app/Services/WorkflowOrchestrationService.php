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
