<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Repositories\IssueRepository;
use App\Services\IssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('builds the issue detail payload for the issue page', function () {
    $repository = Mockery::mock(IssueRepository::class);
    $project = Project::factory()->create(['name' => 'Roadmap']);
    $reporter = User::factory()->create(['name' => 'Reporter']);
    $assignee = User::factory()->create(['name' => 'Assignee']);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $reporter->id,
        'assignee_id' => $assignee->id,
        'title' => 'Ship feature',
        'description' => 'Do the work',
        'type' => 'task',
        'status' => 'todo',
        'priority' => 'high',
    ]);

    $issue->setRelation('reporter', $reporter);
    $issue->setRelation('assignee', $assignee);
    $issue->setRelation('labels', collect([(object) ['id' => 9, 'name' => 'Urgent']]));
    $issue->setRelation('comments', collect([]));
    $issue->setRelation('activities', collect([]));
    $issue->setRelation('runs', collect([]));
    $issue->setRelation('workflowRuns', collect([
        WorkflowRun::factory()->make([
            'id' => 77,
            'status' => 'running',
            'current_step' => 'analysis',
            'metadata' => ['retry_count' => 2, 'last_completed_step' => 'analysis'],
        ]),
    ]));

    Agent::factory()->create([
        'name' => 'Issue Analyzer',
        'slug' => 'issue-analyzer',
        'is_active' => true,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);

    $payload = (new IssueService($repository))->getIssueDetailPayload($project, $issue);

    expect($payload['project']['id'])->toBe($project->id)
        ->and($payload['issue']['id'])->toBe($issue->id)
        ->and($payload['issue']['status'])->toBe('todo')
        ->and($payload['issue']['workflow_runs'])->toHaveCount(1)
        ->and($payload['issue']['agents'])->toHaveCount(1);
});
