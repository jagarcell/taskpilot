<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectGitHubRepository;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Repositories\IssueRepository;
use App\Services\IssueService;
use App\Services\ProjectGitHubIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('resolves the github integration service from the container for issue detail payloads', function () {
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

    $project->setRelation('githubRepository', ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'test_taskpilot',
        'default_branch' => 'main',
        'repository_url' => 'https://github.com/jagarcell/test_taskpilot',
    ]));

    $issue->setRelation('reporter', $reporter);
    $issue->setRelation('assignee', $assignee);
    $issue->setRelation('labels', collect([]));
    $issue->setRelation('comments', collect([]));
    $issue->setRelation('activities', collect([]));
    $issue->setRelation('runs', collect([]));
    $issue->setRelation('workflowRuns', collect([]));

    $service = app(IssueService::class);
    $payload = $service->getIssueDetailPayload($project, $issue);

    expect($payload['project']['github'])->not->toBeNull()
        ->and($payload['project']['github']['pull_request']['state'])->toBe('none');
});

it('builds the issue detail payload for the issue page', function () {
    $repository = Mockery::mock(IssueRepository::class);
    $githubIntegration = Mockery::mock(ProjectGitHubIntegrationService::class);
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

    $project->setRelation('githubRepository', ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
        'repository_url' => 'https://github.com/jagarcell/taskpilot',
    ]));

    $githubIntegration->shouldReceive('getLatestOpenPullRequestStatus')
        ->once()
        ->with($project)
        ->andReturn([
            'owner' => 'jagarcell',
            'repo' => 'taskpilot',
            'number' => 42,
            'state' => 'open',
            'title' => 'docs: add taskpilot description',
            'url' => 'https://github.com/jagarcell/taskpilot/pull/42',
            'head_sha' => 'abc123',
            'base_branch' => 'main',
            'mergeable' => true,
            'checks' => [
                'total' => 2,
                'success' => 1,
                'failure' => 1,
                'pending' => 0,
                'skipped' => 0,
                'overall' => 'failure',
            ],
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

    Agent::factory()->create([
        'name' => 'Planning Agent',
        'slug' => 'planning-agent',
        'is_active' => true,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);

    $payload = (new IssueService($repository, $githubIntegration))->getIssueDetailPayload($project, $issue);

    $agentNames = array_map(fn ($agent) => $agent['name'], $payload['issue']['agents']);

    expect($payload['project']['id'])->toBe($project->id)
        ->and($payload['issue']['id'])->toBe($issue->id)
        ->and($payload['issue']['status'])->toBe('todo')
        ->and($payload['issue']['workflow_runs'])->toHaveCount(1)
        ->and($payload['issue']['agents'])->toHaveCount(2)
        ->and($payload['project']['github'])->toMatchArray([
            'owner' => 'jagarcell',
            'repo' => 'taskpilot',
            'repository_url' => 'https://github.com/jagarcell/taskpilot',
            'pull_request' => [
                'owner' => 'jagarcell',
                'repo' => 'taskpilot',
                'number' => 42,
                'state' => 'open',
                'title' => 'docs: add taskpilot description',
                'url' => 'https://github.com/jagarcell/taskpilot/pull/42',
                'head_sha' => 'abc123',
                'base_branch' => 'main',
                'mergeable' => true,
                'checks' => [
                    'total' => 2,
                    'success' => 1,
                    'failure' => 1,
                    'pending' => 0,
                    'skipped' => 0,
                    'overall' => 'failure',
                ],
            ],
        ])
        ->and($agentNames)->toContain('Issue Analyzer')
        ->and($agentNames)->toContain('Planning Agent')
        ->and(collect($payload['issue']['agents'])->pluck('slug')->all())->toContain('issue-analyzer')
        ->and(collect($payload['issue']['agents'])->pluck('slug')->all())->toContain('planning-agent');
});
