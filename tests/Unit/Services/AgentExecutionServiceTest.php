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

it('keeps realtime payloads minimal and excludes stored output/error data', function () {
    $project = Project::factory()->create();
    $issue = Issue::factory()->create(['project_id' => $project->id]);
    $user = User::factory()->create();
    $agent = Agent::factory()->create();
    $run = AgentRun::factory()->create([
        'issue_id' => $issue->id,
        'agent_id' => $agent->id,
        'user_id' => $user->id,
        'status' => AgentRunStatus::RUNNING,
        'output' => ['summary' => 'very large output payload'],
        'error' => ['trace' => 'very large stack trace'],
    ]);
    $message = app(AgentRunRepository::class)->createMessage($run, 'assistant', 'Final summary only', ['output' => ['summary' => 'huge output msg']]);

    $statusEvent = new AgentRunStatusChanged($run->fresh(), AgentRunStatus::PENDING);
    $messageEvent = new AgentRunMessageAdded($run->fresh(), $message);

    expect($statusEvent->broadcastWith())->toMatchArray([
        'run_id' => $run->id,
        'issue_id' => $issue->id,
        'project_id' => $project->id,
        'status' => AgentRunStatus::RUNNING,
        'previous_status' => AgentRunStatus::PENDING,
    ])
        ->and($statusEvent->broadcastWith())->not->toHaveKey('output')
        ->and($statusEvent->broadcastWith())->not->toHaveKey('error')
        ->and($messageEvent->broadcastWith()['message'])->toMatchArray([
            'id' => $message->id,
            'role' => 'assistant',
            'content' => 'Final summary only',
        ])
        ->and($messageEvent->broadcastWith()['message'])->toHaveKey('created_at')
        ->and($messageEvent->broadcastWith()['message'])->not->toHaveKey('metadata');
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

it('records a concrete file artifact when an implementation agent completes', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'title' => 'Add issue reporter summary to dashboard',
    ]);

    $agent = Agent::factory()->create([
        'name' => 'Implementation Agent',
        'is_active' => true,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'slug' => 'implementation-agent-write-test-'.uniqid('', true),
        'steps' => ['analysis', 'planning', 'approval', 'implementation', 'testing'],
        'config' => ['requires_human_approval' => true],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'implementation',
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
        'input' => ['prompt' => 'Add issue reporter summary to dashboard'],
    ]);

    app(AgentExecutionService::class)->execute($run);

    $fresh = $workflowRun->fresh();
    $files = $fresh->metadata['implementation']['files_changed'] ?? [];

    expect($fresh->status)->toBe('running')
        ->and($files)->toBeArray()
        ->and($files)->not->toBeEmpty()
        ->and(file_exists($files[0]))->toBeTrue();
});

it('records a concrete validation artifact when a testing agent completes', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'title' => 'Add issue reporter summary to dashboard',
    ]);

    $agent = Agent::factory()->create([
        'name' => 'QA Agent',
        'is_active' => true,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'slug' => 'testing-agent-validation-test-'.uniqid('', true),
        'steps' => ['analysis', 'planning', 'approval', 'implementation', 'testing', 'review'],
        'config' => ['requires_human_approval' => true],
    ]);

    $workflowRun = WorkflowRun::factory()->create([
        'workflow_definition_id' => $definition->id,
        'issue_id' => $issue->id,
        'user_id' => $owner->id,
        'current_step' => 'testing',
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
        'input' => ['prompt' => 'Validate the dashboard summary flow'],
    ]);

    app(AgentExecutionService::class)->execute($run);

    $fresh = $workflowRun->fresh();
    $artifacts = $fresh->metadata['testing']['artifacts'] ?? [];

    expect($fresh->status)->toBe('running')
        ->and($artifacts)->toBeArray()
        ->and($artifacts)->not->toBeEmpty()
        ->and(file_exists($artifacts[0]))->toBeTrue();
});

it('records a concrete review artifact when a review agent completes', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'title' => 'Add issue reporter summary to dashboard',
    ]);

    $agent = Agent::factory()->create([
        'name' => 'Review Agent',
        'is_active' => true,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);

    $definition = WorkflowDefinition::factory()->create([
        'slug' => 'review-agent-validation-test-'.uniqid('', true),
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

    $run = AgentRun::factory()->create([
        'issue_id' => $issue->id,
        'agent_id' => $agent->id,
        'user_id' => $owner->id,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'status' => AgentRunStatus::PENDING,
        'input' => ['prompt' => 'Review the dashboard summary changes'],
    ]);

    app(AgentExecutionService::class)->execute($run);

    $fresh = $workflowRun->fresh();
    $artifacts = $fresh->metadata['review']['artifacts'] ?? [];

    expect($fresh->status)->toBe('completed')
        ->and($artifacts)->toBeArray()
        ->and($artifacts)->not->toBeEmpty()
        ->and(file_exists($artifacts[0]))->toBeTrue();
});
