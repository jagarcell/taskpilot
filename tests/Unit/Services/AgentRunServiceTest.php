<?php

namespace Tests\Unit\Services;

use App\Enums\AgentRunStatus;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Issue;
use App\Models\User;
use App\Repositories\AgentRunRepository;
use App\Services\AgentRunService;
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
