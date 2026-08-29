<?php

namespace Tests\Unit\Services;

use App\Enums\AgentRunStatus;
use App\Events\AgentRunStatusChanged;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Repositories\AgentRunRepository;
use App\Services\AgentExecutionService;
use Illuminate\Support\Facades\Event;

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
