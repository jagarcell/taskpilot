<?php

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores a workflow definition with ordered steps and configuration metadata', function () {
    $definition = WorkflowDefinition::create([
        'name' => 'Issue delivery workflow',
        'slug' => 'issue-delivery-workflow',
        'description' => 'Analyze, plan, approve, implement, test, and review a delivery task.',
        'steps' => ['analysis', 'planning', 'approval', 'implementation', 'testing', 'review'],
        'config' => [
            'requires_human_approval' => true,
            'default' => true,
        ],
        'is_enabled' => true,
    ]);

    expect($definition->steps)->toBe(['analysis', 'planning', 'approval', 'implementation', 'testing', 'review'])
        ->and($definition->config)->toMatchArray([
            'requires_human_approval' => true,
            'default' => true,
        ])
        ->and($definition->is_enabled)->toBeTrue();
});

it('creates a workflow run for an issue using a definition and tracks the current step', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    $definition = WorkflowDefinition::create([
        'name' => 'Issue delivery workflow',
        'slug' => 'issue-delivery-workflow',
        'steps' => ['analysis', 'planning', 'approval'],
        'config' => ['requires_human_approval' => true],
    ]);

    $run = WorkflowRun::create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'analysis',
        'status' => 'running',
        'metadata' => [
            'started_from' => 'issue_detail_page',
        ],
    ]);

    expect($run->issue->id)->toBe($issue->id)
        ->and($run->workflowDefinition->id)->toBe($definition->id)
        ->and($run->current_step)->toBe('analysis')
        ->and($run->status)->toBe('running')
        ->and($run->metadata)->toMatchArray([
            'started_from' => 'issue_detail_page',
        ]);
});
