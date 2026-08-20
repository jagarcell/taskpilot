<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\User;
use App\Repositories\ProjectRepository;
use App\Services\ProjectService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

it('lists projects for the current user from the repository', function () {
    $repository = Mockery::mock(ProjectRepository::class);
    $user = User::factory()->make(['id' => 10]);
    $projects = new Collection([
        Project::factory()->make(['id' => 1, 'owner_id' => $user->id]),
    ]);

    $repository->shouldReceive('listForUser')
        ->once()
        ->with($user)
        ->andReturn($projects);

    $service = new ProjectService($repository);

    expect($service->getProjectsForUser($user))->toBe($projects);
});

it('loads a project for an accessible user', function () {
    $repository = Mockery::mock(ProjectRepository::class);
    $user = User::factory()->make(['id' => 10]);
    $project = Project::factory()->make(['id' => 1, 'owner_id' => $user->id]);

    $repository->shouldReceive('getProjectWithRelations')
        ->once()
        ->with($project)
        ->andReturn($project);

    $service = new ProjectService($repository);

    expect($service->getProjectForUser($project, $user))->toBe($project);
});

it('rejects access to a project when the user is not the owner or member', function () {
    $repository = Mockery::mock(ProjectRepository::class);
    $project = Project::factory()->make(['id' => 1, 'owner_id' => 99]);
    $user = User::factory()->make(['id' => 10]);

    $service = new ProjectService($repository);

    expect(fn () => $service->getProjectForUser($project, $user))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('creates a project for the authenticated owner', function () {
    $repository = Mockery::mock(ProjectRepository::class);
    $user = User::factory()->make(['id' => 10]);
    $project = Project::factory()->make(['id' => 1, 'owner_id' => $user->id]);

    $repository->shouldReceive('createForOwner')
        ->once()
        ->with($user, [
            'name' => 'Launch plan',
            'description' => 'Updated scope',
        ])
        ->andReturn($project);

    $service = new ProjectService($repository);

    expect($service->createProject($user, [
        'name' => 'Launch plan',
        'description' => 'Updated scope',
    ]))->toBe($project);
});
