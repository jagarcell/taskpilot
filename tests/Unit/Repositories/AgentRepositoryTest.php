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
