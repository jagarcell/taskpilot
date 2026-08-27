<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts the workflow for an issue from the issue detail flow', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $user->id,
        'assignee_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('projects.issues.workflow-runs.start', [$project, $issue]))
        ->assertRedirect(route('projects.issues.show', [$project, $issue]));

    $this->assertDatabaseHas('workflow_runs', [
        'issue_id' => $issue->id,
        'user_id' => $user->id,
        'current_step' => 'analysis',
        'status' => 'running',
    ]);
});

it('approves the current workflow step for a waiting workflow run', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $user->id,
        'assignee_id' => $user->id,
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'steps' => ['analysis', 'planning', 'approval'],
        'config' => ['requires_human_approval' => true],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $user->id,
        'current_step' => 'approval',
        'status' => 'waiting_for_approval',
        'metadata' => ['execution_history' => []],
    ]);

    $this->actingAs($user)
        ->post(route('projects.issues.workflow-runs.approve', [$project, $issue, $workflowRun]))
        ->assertRedirect(route('projects.issues.show', [$project, $issue]));

    $this->assertDatabaseHas('workflow_runs', [
        'id' => $workflowRun->id,
        'status' => 'running',
        'current_step' => 'approval',
    ]);
});

it('retries the current failed workflow step for a retryable workflow run', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $user->id,
        'assignee_id' => $user->id,
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'steps' => ['analysis', 'planning'],
        'config' => ['requires_human_approval' => false],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $user->id,
        'current_step' => 'planning',
        'status' => 'failed',
        'metadata' => [
            'execution_history' => [],
            'failed_step' => 'planning',
            'retry_count' => 1,
        ],
    ]);

    $this->actingAs($user)
        ->post(route('projects.issues.workflow-runs.retry', [$project, $issue, $workflowRun]))
        ->assertRedirect(route('projects.issues.show', [$project, $issue]));

    $this->assertDatabaseHas('workflow_runs', [
        'id' => $workflowRun->id,
        'status' => 'running',
        'current_step' => 'planning',
    ]);
});
