<?php

namespace Tests\Unit\Services;

use App\Enums\AgentRunStatus;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectRepositoryBinding;
use App\Models\User;
use App\Repositories\AgentRunRepository;
use App\Services\AgentRunService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

it('creates a pending run for an issue', function () {
    $repository = Mockery::mock(AgentRunRepository::class);
    $user = User::factory()->make(['id' => 10]);
    $issue = Issue::factory()->make(['id' => 20]);
    $agent = Agent::factory()->make(['id' => 30]);
    $run = new AgentRun([
        'id' => 99,
        'issue_id' => $issue->id,
        'agent_id' => $agent->id,
        'user_id' => $user->id,
        'model' => 'gpt-4o-mini',
        'provider' => 'openai',
        'status' => AgentRunStatus::PENDING,
        'input' => ['prompt' => 'Summarize the issue'],
    ]);

    $repository->shouldReceive('create')
        ->once()
        ->with($agent, $issue, $user, [
            'model' => 'gpt-4o-mini',
            'provider' => 'openai',
            'input' => ['prompt' => 'Summarize the issue'],
        ])
        ->andReturn($run);

    $service = new AgentRunService($repository);

    expect($service->createRun($agent, $issue, $user, [
        'model' => 'gpt-4o-mini',
        'provider' => 'openai',
        'input' => ['prompt' => 'Summarize the issue'],
    ]))->toBe($run);
});

it('enriches planning prompts with the latest analysis context when creating a run', function () {
    $repository = Mockery::mock(AgentRunRepository::class);
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $user->id,
        'title' => 'Checkout totals are wrong',
        'description' => 'Totals are off.',
    ]);
    AgentRun::factory()->create([
        'issue_id' => $issue->id,
        'user_id' => $user->id,
        'output' => [
            'summary' => 'This issue likely affects arithmetic during cart total updates.',
            'analysis' => [
                'suggested_priority' => 'high',
                'estimated_complexity' => 5,
            ],
        ],
    ]);
    $agent = Agent::factory()->make(['id' => 30, 'name' => 'Planning Agent']);
    $run = new AgentRun([
        'id' => 99,
        'issue_id' => $issue->id,
        'agent_id' => $agent->id,
        'user_id' => $user->id,
        'model' => 'gpt-4o-mini',
        'provider' => 'openai',
        'status' => AgentRunStatus::PENDING,
        'input' => ['prompt' => 'Summarize the issue'],
    ]);

    $runQuery = Mockery::mock(HasMany::class);
    $runQuery->shouldReceive('whereNotNull')->with('output')->andReturnSelf();
    $runQuery->shouldReceive('latest')->andReturnSelf();
    $runQuery->shouldReceive('first')->andReturn(new AgentRun([
        'output' => [
            'summary' => 'This issue likely affects arithmetic during cart total updates.',
            'analysis' => [
                'suggested_priority' => 'high',
                'estimated_complexity' => 5,
            ],
        ],
    ]));

    $repository->shouldReceive('findLatestAnalysisForIssue')
        ->once()
        ->with($issue)
        ->andReturn(new AgentRun([
            'output' => [
                'summary' => 'This issue likely affects arithmetic during cart total updates.',
                'analysis' => [
                    'suggested_priority' => 'high',
                    'estimated_complexity' => 5,
                ],
            ],
        ]));

    $repository->shouldReceive('create')
        ->once()
        ->with($agent, $issue, $user, Mockery::on(function (array $attributes): bool {
            $prompt = $attributes['input']['prompt'] ?? '';

            return str_contains($prompt, 'Summarize the issue')
                && str_contains($prompt, 'Latest analysis context:')
                && str_contains($prompt, 'Suggested priority: high');
        }))
        ->andReturn($run);

    $service = new AgentRunService($repository);

    $service->createRun($agent, $issue, $user, [
        'model' => 'gpt-4o-mini',
        'provider' => 'openai',
        'input' => ['prompt' => 'Summarize the issue'],
    ]);
});

it('enriches agent prompts with the active repository context when a project binding exists', function () {
    $repository = Mockery::mock(AgentRunRepository::class);
    $user = User::factory()->create();
    $project = Project::factory()->create(['name' => 'Platform', 'owner_id' => $user->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $user->id,
        'title' => 'Checkout totals are wrong',
        'description' => 'Totals are off.',
    ]);
    ProjectRepositoryBinding::create([
        'project_id' => $project->id,
        'provider' => 'github',
        'binding_type' => 'remote',
        'remote_owner' => 'acme',
        'remote_repo' => 'platform',
        'remote_url' => 'https://github.com/acme/platform',
        'default_branch' => 'main',
        'is_active' => true,
        'verified_at' => now(),
    ]);

    $agent = Agent::factory()->make(['id' => 30, 'name' => 'Issue Analyzer']);
    $run = new AgentRun([
        'id' => 99,
        'issue_id' => $issue->id,
        'agent_id' => $agent->id,
        'user_id' => $user->id,
        'model' => 'gpt-4o-mini',
        'provider' => 'openai',
        'status' => AgentRunStatus::PENDING,
        'input' => ['prompt' => 'Summarize the issue'],
    ]);

    $repository->shouldReceive('create')
        ->once()
        ->with($agent, $issue, $user, Mockery::on(function (array $attributes): bool {
            $prompt = $attributes['input']['prompt'] ?? '';

            return str_contains($prompt, 'Repository context:')
                && str_contains($prompt, 'Project: Platform')
                && str_contains($prompt, 'Remote repository: acme/platform')
                && str_contains($prompt, 'Default branch: main');
        }))
        ->andReturn($run);

    $service = new AgentRunService($repository);

    $service->createRun($agent, $issue, $user, [
        'model' => 'gpt-4o-mini',
        'provider' => 'openai',
        'input' => ['prompt' => 'Summarize the issue'],
    ]);
});

it('dispatches a queued execution job when a run is created', function () {
    Queue::fake();

    $repository = Mockery::mock(AgentRunRepository::class);
    $user = User::factory()->make(['id' => 10]);
    $issue = Issue::factory()->make(['id' => 20]);
    $agent = Agent::factory()->make(['id' => 30]);
    $run = new AgentRun([
        'id' => 99,
        'issue_id' => $issue->id,
        'agent_id' => $agent->id,
        'user_id' => $user->id,
        'model' => 'gpt-4o-mini',
        'provider' => 'openai',
        'status' => AgentRunStatus::PENDING,
        'input' => ['prompt' => 'Summarize the issue'],
    ]);

    $repository->shouldReceive('create')
        ->once()
        ->with($agent, $issue, $user, [
            'model' => 'gpt-4o-mini',
            'provider' => 'openai',
            'input' => ['prompt' => 'Summarize the issue'],
        ])
        ->andReturn($run);

    $service = new AgentRunService($repository);

    $service->createRun($agent, $issue, $user, [
        'model' => 'gpt-4o-mini',
        'provider' => 'openai',
        'input' => ['prompt' => 'Summarize the issue'],
    ]);

    Queue::assertPushed(ExecuteAgentRunJob::class, fn (ExecuteAgentRunJob $job) => $job->agentRun->id === $run->id);
});

it('marks a run as completed with output', function () {
    $repository = Mockery::mock(AgentRunRepository::class);
    $run = new AgentRun([
        'id' => 100,
        'status' => AgentRunStatus::RUNNING,
        'output' => null,
    ]);

    $repository->shouldReceive('updateStatus')
        ->once()
        ->with($run, AgentRunStatus::COMPLETED, ['output' => ['summary' => 'Ready']])
        ->andReturn($run);

    $service = new AgentRunService($repository);

    expect($service->markRunAsCompleted($run, ['summary' => 'Ready']))->toBe($run);
});
