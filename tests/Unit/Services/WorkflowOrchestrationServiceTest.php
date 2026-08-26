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
