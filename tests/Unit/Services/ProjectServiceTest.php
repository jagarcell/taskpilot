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

it('builds the project detail payload for the issue dashboard', function () {
    $repository = Mockery::mock(ProjectRepository::class);
    $owner = User::factory()->make(['id' => 10, 'name' => 'Owner User', 'email' => 'owner@example.com']);
    $member = User::factory()->make(['id' => 11, 'name' => 'Member User', 'email' => 'member@example.com']);
    $project = Project::factory()->make([
        'id' => 42,
        'name' => 'Roadmap',
        'description' => 'Product work',
        'owner_id' => $owner->id,
    ]);
    $project->setRelation('owner', $owner);
    $project->setRelation('members', collect([
        (object) ['id' => 7, 'user_id' => $member->id, 'role' => 'member', 'user' => $member],
    ]));
    $project->setRelation('labels', collect([
        (object) ['id' => 5, 'name' => 'Urgent'],
    ]));
    $project->setRelation('issues', collect([
        (object) [
            'id' => 99,
            'issue_key' => 'ROAD-1',
            'title' => 'Ship feature',
            'description' => 'User flow',
            'type' => 'feature',
            'status' => 'todo',
            'priority' => 'high',
            'assignee_id' => $member->id,
            'assignee' => $member,
            'labels' => collect([(object) ['id' => 5, 'name' => 'Urgent']]),
            'comments' => collect([]),
        ],
    ]));

    $repository->shouldReceive('getProjectWithRelations')
        ->once()
        ->with($project)
        ->andReturnUsing(function ($projectToLoad) use ($project) {
            return $project;
        });

    $service = new ProjectService($repository);

    $loadedProject = $service->getProjectForUser($project, $owner);
    $payload = $service->getProjectDetailPayload($loadedProject, $owner);

    expect($payload['project']['id'])->toBe(42)
        ->and($payload['issues'])->toHaveCount(1)
        ->and($payload['issues_by_status']['todo'])->toHaveCount(1)
        ->and($payload['assignees'])->toHaveCount(2)
        ->and($payload['project']['can_manage_project'])->toBeTrue();
});

it('includes the active github repository and pull request status in the project dashboard payload', function () {
    $repository = Mockery::mock(ProjectRepository::class);
    $githubService = Mockery::mock(\App\Services\ProjectGitHubIntegrationService::class);
    $owner = User::factory()->make(['id' => 10, 'name' => 'Owner User', 'email' => 'owner@example.com']);
    $project = Project::factory()->make([
        'id' => 42,
        'name' => 'Roadmap',
        'description' => 'Product work',
        'owner_id' => $owner->id,
    ]);
    $project->setRelation('owner', $owner);
    $project->setRelation('members', collect([]));
    $project->setRelation('labels', collect([]));
    $project->setRelation('issues', collect([]));
    $project->setRelation('githubRepository', (object) [
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
        'repository_url' => 'https://github.com/jagarcell/taskpilot',
        'is_active' => true,
    ]);

    $githubService->shouldReceive('getLatestOpenPullRequestStatus')
        ->once()
        ->with($project)
        ->andReturn([
            'owner' => 'jagarcell',
            'repo' => 'taskpilot',
            'number' => 42,
            'state' => 'open',
            'title' => 'docs: add taskpilot description',
            'url' => 'https://github.com/jagarcell/taskpilot/pull/42',
            'head_sha' => 'abc123',
            'base_branch' => 'main',
            'mergeable' => true,
            'checks' => [
                'total' => 2,
                'success' => 1,
                'failure' => 1,
                'pending' => 0,
                'skipped' => 0,
                'overall' => 'failure',
            ],
        ]);

    $repository->shouldReceive('getProjectWithRelations')
        ->once()
        ->with($project)
        ->andReturnUsing(function ($projectToLoad) use ($project) {
            return $project;
        });

    $service = new ProjectService($repository, $githubService);
    $loadedProject = $service->getProjectForUser($project, $owner);
    $payload = $service->getProjectDetailPayload($loadedProject, $owner);

    expect($payload['project']['github'])->toMatchArray([
        'owner' => 'jagarcell',
        'repo' => 'taskpilot',
        'default_branch' => 'main',
        'repository_url' => 'https://github.com/jagarcell/taskpilot',
        'is_active' => true,
        'pull_request' => [
            'owner' => 'jagarcell',
            'repo' => 'taskpilot',
            'number' => 42,
            'state' => 'open',
            'title' => 'docs: add taskpilot description',
            'url' => 'https://github.com/jagarcell/taskpilot/pull/42',
            'head_sha' => 'abc123',
            'base_branch' => 'main',
            'mergeable' => true,
            'checks' => [
                'total' => 2,
                'success' => 1,
                'failure' => 1,
                'pending' => 0,
                'skipped' => 0,
                'overall' => 'failure',
            ],
        ],
    ]);
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
