<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\ProjectRepositoryBinding;
use App\Repositories\ProjectRepositoryBindingRepository;
use App\Services\ProjectRepositoryBindingService;
use Mockery;
use Tests\TestCase;

it('stores a project repository binding for the project', function () {
    $repository = Mockery::mock(ProjectRepositoryBindingRepository::class);
    $project = Project::factory()->make(['id' => 12, 'owner_id' => 99]);
    $binding = new ProjectRepositoryBinding([
        'project_id' => $project->id,
        'provider' => 'github',
        'binding_type' => 'remote',
        'remote_owner' => 'jagarcell',
        'remote_repo' => 'taskpilot',
        'remote_url' => 'https://github.com/jagarcell/taskpilot',
        'default_branch' => 'main',
        'is_active' => true,
    ]);

    $repository->shouldReceive('bind')
        ->once()
        ->with($project, [
            'provider' => 'github',
            'binding_type' => 'remote',
            'remote_owner' => 'jagarcell',
            'remote_repo' => 'taskpilot',
            'remote_url' => 'https://github.com/jagarcell/taskpilot',
            'default_branch' => 'main',
            'is_active' => true,
        ])
        ->andReturn($binding);

    $service = new ProjectRepositoryBindingService($repository);

    expect($service->bind($project, [
        'provider' => 'github',
        'binding_type' => 'remote',
        'remote_owner' => 'jagarcell',
        'remote_repo' => 'taskpilot',
        'remote_url' => 'https://github.com/jagarcell/taskpilot',
        'default_branch' => 'main',
        'is_active' => true,
    ]))->toBe($binding);
});

it('returns the configured repository binding for the project', function () {
    $repository = Mockery::mock(ProjectRepositoryBindingRepository::class);
    $project = Project::factory()->make(['id' => 12, 'owner_id' => 99]);
    $binding = new ProjectRepositoryBinding([
        'project_id' => $project->id,
        'provider' => 'github',
        'binding_type' => 'remote',
        'remote_owner' => 'jagarcell',
        'remote_repo' => 'taskpilot',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($binding);

    $service = new ProjectRepositoryBindingService($repository);

    expect($service->getForProject($project))->toBe($binding);
});
