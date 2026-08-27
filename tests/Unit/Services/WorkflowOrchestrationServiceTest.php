<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use App\Services\WorkflowOrchestrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves the next step in the default workflow sequence', function () {
    $definition = WorkflowDefinition::factory()->make([
        'steps' => ['analysis', 'planning', 'approval'],
    ]);

    $service = app(WorkflowOrchestrationService::class);

    expect($service->resolveNextStep($definition, null))->toBe('analysis')
        ->and($service->resolveNextStep($definition, 'analysis'))->toBe('planning')
        ->and($service->resolveNextStep($definition, 'planning'))->toBe('approval')
        ->and($service->resolveNextStep($definition, 'approval'))->toBe('approval');
});

it('starts a workflow run and launches the issue analyzer agent first', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    Agent::factory()->create([
        'name' => 'Issue Analyzer',
        'is_active' => true,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);

    Agent::factory()->create([
        'name' => 'Planning Agent',
        'is_active' => true,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'name' => 'Issue workflow',
        'slug' => 'issue-workflow',
        'steps' => ['analysis', 'planning', 'approval'],
        'config' => ['default' => true],
    ]);

    $service = app(WorkflowOrchestrationService::class);
    $workflowRun = $service->startIssueWorkflow($issue, $owner, $definition);

    expect($workflowRun)->toBeInstanceOf(WorkflowRun::class)
        ->and($workflowRun->issue_id)->toBe($issue->id)
        ->and($workflowRun->current_step)->toBe('analysis')
        ->and($workflowRun->status)->toBe('running')
        ->and($workflowRun->workflow_definition_id)->toBe($definition->id)
        ->and(AgentRun::query()->where('issue_id', $issue->id)->count())->toBe(1)
        ->and(AgentRun::query()->where('issue_id', $issue->id)->first()->agent->name)->toBe('Issue Analyzer');
});

it('advances the workflow to planning after the analyzer completes and waits for approval before implementation', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    $issueAnalyzer = Agent::factory()->create([
        'name' => 'Issue Analyzer',
        'is_active' => true,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);

    Agent::factory()->create([
        'name' => 'Planning Agent',
        'is_active' => true,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'steps' => ['analysis', 'planning', 'approval'],
        'config' => ['requires_human_approval' => true],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'analysis',
        'status' => 'running',
    ]);

    $service = app(WorkflowOrchestrationService::class);
    $service->advanceWorkflow($workflowRun, 'analysis');

    expect($workflowRun->fresh()->current_step)->toBe('planning')
        ->and($workflowRun->fresh()->status)->toBe('running')
        ->and(AgentRun::query()->where('issue_id', $issue->id)->count())->toBe(1);

    $service->advanceWorkflow($workflowRun->fresh(), 'planning');

    expect($workflowRun->fresh()->current_step)->toBe('approval')
        ->and($workflowRun->fresh()->status)->toBe('waiting_for_approval');
});

it('can approve the current workflow step and continue the sequence', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    Agent::factory()->create([
        'name' => 'Implementation Agent',
        'is_active' => true,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'steps' => ['analysis', 'planning', 'approval', 'implementation'],
        'config' => ['requires_human_approval' => true],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'approval',
        'status' => 'waiting_for_approval',
    ]);

    $service = app(WorkflowOrchestrationService::class);
    $service->approveCurrentStep($workflowRun);

    expect($workflowRun->fresh()->current_step)->toBe('implementation')
        ->and($workflowRun->fresh()->status)->toBe('running');
});

it('marks a failed workflow step and preserves retry metadata', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'steps' => ['analysis', 'planning', 'approval'],
        'config' => ['requires_human_approval' => true],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'analysis',
        'status' => 'running',
        'metadata' => ['last_completed_step' => null],
    ]);

    $service = app(WorkflowOrchestrationService::class);
    $service->markFailed($workflowRun, 'analysis', [
        'message' => 'Analyzer exceeded the timeout.',
    ]);

    expect($workflowRun->fresh()->status)->toBe('failed')
        ->and($workflowRun->fresh()->current_step)->toBe('analysis')
        ->and($workflowRun->fresh()->metadata['failed_step'])->toBe('analysis')
        ->and($workflowRun->fresh()->metadata['last_error']['message'])->toBe('Analyzer exceeded the timeout.');
});

it('retries a failed workflow step and clears the failure state', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'steps' => ['analysis', 'planning', 'approval'],
        'config' => ['requires_human_approval' => true],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'analysis',
        'status' => 'failed',
        'metadata' => [
            'failed_step' => 'analysis',
            'retry_count' => 1,
            'last_error' => ['message' => 'Analyzer exceeded the timeout.'],
        ],
    ]);

    $service = app(WorkflowOrchestrationService::class);
    $service->retryCurrentStep($workflowRun);

    expect($workflowRun->fresh()->status)->toBe('running')
        ->and($workflowRun->fresh()->current_step)->toBe('analysis')
        ->and($workflowRun->fresh()->metadata['retry_count'])->toBe(2)
        ->and($workflowRun->fresh()->metadata['failed_step'])->toBeNull();
});

it('records the execution history for workflow-stage transitions and retries', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'steps' => ['analysis', 'planning', 'approval'],
        'config' => ['requires_human_approval' => true],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'analysis',
        'status' => 'running',
        'metadata' => ['started_from' => 'issue_detail_page'],
    ]);

    $service = app(WorkflowOrchestrationService::class);
    $service->advanceWorkflow($workflowRun, 'analysis');
    $service->markFailed($workflowRun->fresh(), 'planning', ['message' => 'Planning failed.']);
    $service->retryCurrentStep($workflowRun->fresh());

    $fresh = $workflowRun->fresh();
    $history = $fresh->metadata['execution_history'];

    expect($history)->toBeArray()
        ->and($history)->toHaveCount(3)
        ->and($history[0])->toMatchArray([
            'step' => 'analysis',
            'event' => 'completed',
            'status' => 'running',
        ])
        ->and($history[1])->toMatchArray([
            'step' => 'planning',
            'event' => 'failed',
            'status' => 'failed',
            'message' => 'Planning failed.',
        ])
        ->and($history[2])->toMatchArray([
            'step' => 'planning',
            'event' => 'retried',
            'status' => 'running',
        ]);
});

it('exposes workflow operator actions for approval and retry state', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'steps' => ['analysis', 'planning', 'approval', 'implementation'],
        'config' => ['requires_human_approval' => true],
    ]);

    $waitingRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'approval',
        'status' => 'waiting_for_approval',
        'metadata' => ['failed_step' => null],
    ]);

    $failedRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'planning',
        'status' => 'failed',
        'metadata' => ['failed_step' => 'planning', 'retry_count' => 1],
    ]);

    expect($waitingRun->isWaitingForApproval())->toBeTrue()
        ->and($waitingRun->canRetry())->toBeFalse()
        ->and($waitingRun->currentOperatorAction())->toBe('approve')
        ->and($failedRun->isWaitingForApproval())->toBeFalse()
        ->and($failedRun->canRetry())->toBeTrue()
        ->and($failedRun->currentOperatorAction())->toBe('retry');
});

it('marks the workflow as completed when the final stage finishes', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'steps' => ['analysis', 'planning', 'approval', 'implementation', 'testing', 'review'],
        'config' => ['requires_human_approval' => true],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'review',
        'status' => 'running',
        'metadata' => ['execution_history' => []],
    ]);

    $service = app(WorkflowOrchestrationService::class);
    $service->advanceWorkflow($workflowRun, 'review');

    $fresh = $workflowRun->fresh();
    $history = $fresh->metadata['execution_history'];

    expect($fresh->status)->toBe('completed')
        ->and($fresh->current_step)->toBe('review')
        ->and($fresh->isCompleted())->toBeTrue()
        ->and($fresh->metadata['completed_at'])->not->toBeNull()
        ->and($history)->toHaveCount(1)
        ->and($history[0])->toMatchArray([
            'step' => 'review',
            'event' => 'completed',
            'status' => 'completed',
        ]);
});

it('exposes a stable issue-level workflow summary for the current state', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'steps' => ['analysis', 'planning', 'approval', 'implementation'],
        'config' => ['requires_human_approval' => true],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'approval',
        'status' => 'waiting_for_approval',
        'metadata' => [
            'execution_history' => [],
            'last_error' => ['message' => 'Planner timed out'],
            'retry_count' => 2,
        ],
    ]);

    $summary = $workflowRun->summary();

    expect($summary)->toMatchArray([
        'status' => 'waiting_for_approval',
        'current_step' => 'approval',
        'last_completed_step' => null,
        'operator_action' => 'approve',
        'is_completed' => false,
        'can_retry' => false,
        'last_error' => ['message' => 'Planner timed out'],
        'retry_count' => 2,
    ]);
});

it('blocks workflow progression when required upstream dependencies have not completed', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'steps' => ['analysis', 'planning', 'approval'],
        'config' => [
            'requires_human_approval' => true,
            'dependencies' => [
                'planning' => ['analysis'],
                'approval' => ['planning'],
            ],
        ],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'planning',
        'status' => 'running',
        'metadata' => [
            'execution_history' => [],
            'last_completed_step' => null,
        ],
    ]);

    $service = app(WorkflowOrchestrationService::class);
    $service->advanceWorkflow($workflowRun, 'planning');

    expect($workflowRun->fresh()->current_step)->toBe('planning')
        ->and($workflowRun->fresh()->status)->toBe('running');
});

it('validates workflow definitions and falls back safely for malformed step sequences', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    $service = app(WorkflowOrchestrationService::class);

    $validDefinition = WorkflowDefinition::factory()->make([
        'steps' => ['analysis', 'planning', 'approval'],
        'config' => ['requires_human_approval' => true],
    ]);

    expect($service->hasValidDefinition($validDefinition))->toBeTrue()
        ->and($service->resolveNextStep($validDefinition, 'planning'))->toBe('approval');

    $invalidDefinition = WorkflowDefinition::factory()->make([
        'steps' => [],
        'config' => ['requires_human_approval' => true],
    ]);

    expect($service->hasValidDefinition($invalidDefinition))->toBeFalse()
        ->and($service->resolveNextStep($invalidDefinition, null))->toBe('');

    $fallback = $service->ensureValidDefinition($issue, $owner, null);

    expect($fallback)->toBeInstanceOf(WorkflowDefinition::class)
        ->and($fallback->steps)->toBe(['analysis', 'planning', 'approval'])
        ->and($fallback->is_enabled)->toBeTrue();
});
