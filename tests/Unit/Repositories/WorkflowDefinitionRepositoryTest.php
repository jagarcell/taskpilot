<?php

namespace Tests\Unit\Repositories;

use App\Models\WorkflowDefinition;
use App\Repositories\WorkflowDefinitionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds the enabled default workflow definition', function () {
    WorkflowDefinition::factory()->create([
        'name' => 'Default issue workflow',
        'slug' => 'default-issue-workflow',
        'steps' => ['analysis', 'planning', 'approval'],
        'config' => ['default' => true],
        'is_enabled' => true,
    ]);

    $repository = app(WorkflowDefinitionRepository::class);

    expect($repository->findDefaultEnabled())->toBeInstanceOf(WorkflowDefinition::class)
        ->and($repository->findDefaultEnabled()->slug)->toBe('default-issue-workflow');
});

it('creates a default workflow definition when none is enabled', function () {
    $repository = app(WorkflowDefinitionRepository::class);

    $definition = $repository->createDefault();

    expect($definition)->toBeInstanceOf(WorkflowDefinition::class)
        ->and($definition->steps)->toBe(['analysis', 'planning', 'approval'])
        ->and($definition->config['default'])->toBeTrue()
        ->and($definition->is_enabled)->toBeTrue();
});
