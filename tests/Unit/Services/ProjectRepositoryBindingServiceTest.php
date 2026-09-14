<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\ProjectRepositoryBinding;
use App\Repositories\ProjectRepositoryBindingRepository;
use App\Services\ProjectGitHubIntegrationService;
use App\Services\ProjectRepositoryBindingService;
use Mockery;
use RuntimeException;
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

it('validates a github repository binding and stores verification metadata', function () {
    $repository = Mockery::mock(ProjectRepositoryBindingRepository::class);
    $githubIntegration = Mockery::mock(ProjectGitHubIntegrationService::class);
    $project = Project::factory()->make(['id' => 12, 'owner_id' => 99]);
    $binding = new ProjectRepositoryBinding([
        'project_id' => $project->id,
        'provider' => 'github',
        'binding_type' => 'remote',
        'remote_owner' => 'jagarcell',
        'remote_repo' => 'taskpilot',
        'default_branch' => 'main',
        'is_active' => true,
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($binding);

    $githubIntegration->shouldReceive('inspectRepository')
        ->once()
        ->with($project)
        ->andReturn([
            'owner' => 'jagarcell',
            'repo' => 'taskpilot',
            'default_branch' => 'main',
            'repository_url' => 'https://github.com/jagarcell/taskpilot',
            'is_private' => false,
            'is_archived' => false,
            'is_valid' => true,
        ]);

    $repository->shouldReceive('bind')
        ->once()
        ->with($project, Mockery::on(function (array $attributes) {
            return $attributes['provider'] === 'github'
                && $attributes['binding_type'] === 'remote'
                && $attributes['remote_owner'] === 'jagarcell'
                && $attributes['remote_repo'] === 'taskpilot'
                && $attributes['default_branch'] === 'main'
                && $attributes['is_active'] === true
                && isset($attributes['verified_at'])
                && $attributes['verified_at'] !== null;
        }))
        ->andReturn($binding);

    $service = new ProjectRepositoryBindingService($repository, $githubIntegration);

    expect($service->validate($project))->toMatchArray([
        'provider' => 'github',
        'binding_type' => 'remote',
        'remote_owner' => 'jagarcell',
        'remote_repo' => 'taskpilot',
        'valid' => true,
        'default_branch' => 'main',
    ]);
});

it('returns a clear validation failure when the remote repository is unavailable', function () {
    $repository = Mockery::mock(ProjectRepositoryBindingRepository::class);
    $githubIntegration = Mockery::mock(ProjectGitHubIntegrationService::class);
    $project = Project::factory()->make(['id' => 12, 'owner_id' => 99]);
    $binding = new ProjectRepositoryBinding([
        'project_id' => $project->id,
        'provider' => 'github',
        'binding_type' => 'remote',
        'remote_owner' => 'jagarcell',
        'remote_repo' => 'missing-repo',
        'default_branch' => 'main',
        'is_active' => true,
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($binding);

    $githubIntegration->shouldReceive('inspectRepository')
        ->once()
        ->with($project)
        ->andThrow(new RuntimeException('Could not inspect GitHub repository: jagarcell/missing-repo'));

    $repository->shouldReceive('bind')
        ->never();

    $service = new ProjectRepositoryBindingService($repository, $githubIntegration);

    expect($service->validate($project))->toMatchArray([
        'provider' => 'github',
        'binding_type' => 'remote',
        'remote_owner' => 'jagarcell',
        'remote_repo' => 'missing-repo',
        'valid' => false,
    ]);
});

it('validates a local repository binding and stores verification metadata', function () {
    $repository = Mockery::mock(ProjectRepositoryBindingRepository::class);
    $githubIntegration = Mockery::mock(ProjectGitHubIntegrationService::class);
    $project = Project::factory()->make(['id' => 12, 'owner_id' => 99]);
    $repoPath = sys_get_temp_dir().'/taskpilot-local-binding-'.uniqid('', true);
    mkdir($repoPath, 0777, true);
    mkdir($repoPath.'/.git', 0777, true);

    $binding = new ProjectRepositoryBinding([
        'project_id' => $project->id,
        'provider' => 'github',
        'binding_type' => 'local',
        'local_path' => $repoPath,
        'is_active' => true,
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($binding);

    $repository->shouldReceive('bind')
        ->once()
        ->with($project, Mockery::on(function (array $attributes) {
            return $attributes['binding_type'] === 'local'
                && $attributes['local_path'] !== ''
                && $attributes['is_active'] === true
                && isset($attributes['verified_at'])
                && $attributes['verified_at'] !== null;
        }))
        ->andReturn($binding);

    $service = new ProjectRepositoryBindingService($repository, $githubIntegration);

    expect($service->validateLocal($project))->toMatchArray([
        'provider' => 'github',
        'binding_type' => 'local',
        'local_path' => $repoPath,
        'valid' => true,
    ]);
});

it('returns a clear validation failure when the local repository path is not a git repository', function () {
    $repository = Mockery::mock(ProjectRepositoryBindingRepository::class);
    $githubIntegration = Mockery::mock(ProjectGitHubIntegrationService::class);
    $project = Project::factory()->make(['id' => 12, 'owner_id' => 99]);
    $repoPath = sys_get_temp_dir().'/taskpilot-local-binding-missing-'.uniqid('', true);
    mkdir($repoPath, 0777, true);

    $binding = new ProjectRepositoryBinding([
        'project_id' => $project->id,
        'provider' => 'github',
        'binding_type' => 'local',
        'local_path' => $repoPath,
        'is_active' => true,
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($binding);

    $repository->shouldReceive('bind')
        ->never();

    $service = new ProjectRepositoryBindingService($repository, $githubIntegration);

    expect($service->validateLocal($project))->toMatchArray([
        'provider' => 'github',
        'binding_type' => 'local',
        'local_path' => $repoPath,
        'valid' => false,
    ]);
});
