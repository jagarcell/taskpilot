<?php

namespace Tests\Unit\Services;

use App\Enums\AgentRunStatus;
use App\Events\AgentRunMessageAdded;
use App\Events\AgentRunStatusChanged;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use App\Repositories\AgentRunRepository;
use App\Services\AgentExecutionService;
use Illuminate\Support\Facades\Event;
use RuntimeException;

it('ignores the QA workflow failure toggle while running automated tests', function () {
    putenv('WORKFLOW_FORCE_FAILURE=true');
    $_ENV['WORKFLOW_FORCE_FAILURE'] = 'true';
    $_SERVER['WORKFLOW_FORCE_FAILURE'] = 'true';

    $service = app(AgentExecutionService::class);

    expect(app()->environment())->toBe('testing')
        ->and($service->shouldForceWorkflowFailure())->toBeFalse();

    putenv('WORKFLOW_FORCE_FAILURE=false');
    $_ENV['WORKFLOW_FORCE_FAILURE'] = 'false';
    $_SERVER['WORKFLOW_FORCE_FAILURE'] = 'false';
});

it('fires a realtime status change event when an agent run transitions status', function () {
    Event::fake();

    $project = Project::factory()->create();
    $issue = Issue::factory()->create(['project_id' => $project->id]);
    $user = User::factory()->create();
    $agent = Agent::factory()->create();
    $run = AgentRun::factory()->create([
        'issue_id' => $issue->id,
        'agent_id' => $agent->id,
        'user_id' => $user->id,
        'status' => AgentRunStatus::PENDING,
    ]);

    app(AgentRunRepository::class)->updateStatus($run, AgentRunStatus::RUNNING);

    Event::assertDispatched(AgentRunStatusChanged::class, function (AgentRunStatusChanged $event) use ($run) {
        return $event->agentRun->id === $run->id
            && $event->previousStatus === AgentRunStatus::PENDING
            && $event->agentRun->status === AgentRunStatus::RUNNING;
    });
});

it('emits a realtime progress event when an agent run records a new message', function () {
    Event::fake();

    $project = Project::factory()->create();
    $issue = Issue::factory()->create(['project_id' => $project->id]);
    $user = User::factory()->create();
    $agent = Agent::factory()->create();
    $run = AgentRun::factory()->create([
        'issue_id' => $issue->id,
        'agent_id' => $agent->id,
        'user_id' => $user->id,
        'status' => AgentRunStatus::RUNNING,
    ]);

    app(AgentRunRepository::class)->createMessage($run, 'assistant', 'Inspecting the failing request path.');

    Event::assertDispatched(AgentRunMessageAdded::class, function (AgentRunMessageAdded $event) use ($run) {
        return $event->agentRun->id === $run->id
            && $event->message->role === 'assistant'
            && $event->message->content === 'Inspecting the failing request path.';
    });
});

it('marks the workflow as failed when a queued job crashes', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
    ]);

    $agent = Agent::factory()->create([
        'name' => 'Issue Analyzer',
        'is_active' => true,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'slug' => 'queued-job-crash-retry-test-'.uniqid('', true),
        'steps' => ['analysis', 'planning', 'approval'],
        'config' => ['requires_human_approval' => true],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'analysis',
        'status' => 'running',
        'metadata' => ['execution_history' => []],
    ]);

    $run = AgentRun::factory()->create([
        'issue_id' => $issue->id,
        'agent_id' => $agent->id,
        'user_id' => $owner->id,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'status' => AgentRunStatus::PENDING,
    ]);

    $job = new ExecuteAgentRunJob($run);
    $job->failed(new RuntimeException('Queue worker crashed while processing the agent run.'));

    expect($run->fresh()->status)->toBe(AgentRunStatus::FAILED)
        ->and($workflowRun->fresh()->status)->toBe('failed')
        ->and($workflowRun->fresh()->current_step)->toBe('analysis')
        ->and($workflowRun->fresh()->canRetry())->toBeTrue();
});
