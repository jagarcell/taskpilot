<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\ProjectGitHubRepository;
use App\Repositories\ProjectGitHubRepositoryRepository;
use App\Services\ProjectGitHubIntegrationService;
use Mockery;
use Tests\TestCase;

it('stores a github repository connection for the project owner', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
    ]);

    $repository->shouldReceive('connect')
        ->once()
        ->with($project, [
            'github_owner' => 'jagarcell',
            'github_repo' => 'taskpilot',
            'default_branch' => 'main',
        ])
        ->andReturn($connection);

    $service = new ProjectGitHubIntegrationService($repository);

    expect($service->connect($project, [
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
    ]))->toBe($connection);
});

it('returns the configured repository connection for the project', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($connection);

    $service = new ProjectGitHubIntegrationService($repository);

    expect($service->getForProject($project))->toBe($connection);
});
