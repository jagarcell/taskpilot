<?php

namespace Tests\Unit\Repositories;

use App\Models\Agent;
use App\Repositories\AgentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds an active agent by name', function () {
    Agent::factory()->create([
        'name' => 'Issue Analyzer',
        'slug' => 'issue-analyzer',
        'is_active' => false,
    ]);

    $active = Agent::factory()->create([
        'name' => 'Issue Analyzer',
        'slug' => 'issue-analyzer-active',
        'is_active' => true,
    ]);

    $repository = app(AgentRepository::class);

    expect($repository->findActiveByName('Issue Analyzer'))->toBeInstanceOf(Agent::class)
        ->and($repository->findActiveByName('Issue Analyzer')->id)->toBe($active->id);
});

it('detects the workflow persona for agent runs from the repository layer', function () {
    $repository = app(AgentRepository::class);
    $implementationAgent = Agent::factory()->create(['name' => 'Implementation Agent']);
    $testingAgent = Agent::factory()->create(['name' => 'QA Agent']);
    $reviewAgent = Agent::factory()->create(['name' => 'Review Agent']);
    $issue = \App\Models\Issue::factory()->create();
    $user = \App\Models\User::factory()->create();

    $implementationRun = \App\Models\AgentRun::factory()->create([
        'agent_id' => $implementationAgent->id,
        'issue_id' => $issue->id,
        'user_id' => $user->id,
    ]);
    $testingRun = \App\Models\AgentRun::factory()->create([
        'agent_id' => $testingAgent->id,
        'issue_id' => $issue->id,
        'user_id' => $user->id,
    ]);
    $reviewRun = \App\Models\AgentRun::factory()->create([
        'agent_id' => $reviewAgent->id,
        'issue_id' => $issue->id,
        'user_id' => $user->id,
    ]);

    expect($repository->isImplementationAgentRun($implementationRun))->toBeTrue()
        ->and($repository->isTestingAgentRun($testingRun))->toBeTrue()
        ->and($repository->isReviewAgentRun($reviewRun))->toBeTrue()
        ->and($repository->isImplementationAgentRun($testingRun))->toBeFalse();
});
