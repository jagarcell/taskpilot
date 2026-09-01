<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\ProjectGitHubRepository;
use App\Repositories\ProjectGitHubRepositoryRepository;
use App\Services\ProjectGitHubIntegrationService;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
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

it('inspects the connected github repository and returns normalized metadata', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'develop',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($connection);

    Http::fake([
        'https://api.github.com/repos/jagarcell/taskpilot' => Http::response([
            'full_name' => 'jagarcell/taskpilot',
            'default_branch' => 'main',
            'html_url' => 'https://github.com/jagarcell/taskpilot',
            'private' => false,
            'archived' => false,
        ], 200),
    ]);

    $service = new ProjectGitHubIntegrationService($repository);

    expect($service->inspectRepository($project))->toMatchArray([
        'owner' => 'jagarcell',
        'repo' => 'taskpilot',
        'default_branch' => 'main',
        'repository_url' => 'https://github.com/jagarcell/taskpilot',
        'is_private' => false,
        'is_archived' => false,
        'is_valid' => true,
    ]);
});

it('throws a helpful exception when the configured github repository cannot be inspected', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'missing-repo',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($connection);

    Http::fake([
        'https://api.github.com/repos/jagarcell/missing-repo' => Http::response([
            'message' => 'Not Found',
        ], 404),
    ]);

    $service = new ProjectGitHubIntegrationService($repository);

    expect(fn () => $service->inspectRepository($project))
        ->toThrow(RuntimeException::class, 'Could not inspect GitHub repository');
});

it('creates a github branch from the configured default branch', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($connection);

    Http::fake([
        'https://api.github.com/repos/jagarcell/taskpilot/git/ref/heads/main' => Http::response([
            'ref' => 'refs/heads/main',
            'object' => ['sha' => 'abc123'],
        ], 200),
        'https://api.github.com/repos/jagarcell/taskpilot/git/refs' => Http::response([
            'ref' => 'refs/heads/feature/taskpilot-github-branch',
            'object' => ['sha' => 'abc123'],
        ], 201),
    ]);

    $service = new ProjectGitHubIntegrationService($repository);

    expect($service->createBranch($project, 'feature/taskpilot-github-branch'))->toMatchArray([
        'owner' => 'jagarcell',
        'repo' => 'taskpilot',
        'branch_name' => 'feature/taskpilot-github-branch',
        'base_branch' => 'main',
        'sha' => 'abc123',
        'created' => true,
    ]);
});

it('throws a helpful exception when creating a github branch fails', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($connection);

    Http::fake([
        'https://api.github.com/repos/jagarcell/taskpilot/git/ref/heads/main' => Http::response([
            'ref' => 'refs/heads/main',
            'object' => ['sha' => 'abc123'],
        ], 200),
        'https://api.github.com/repos/jagarcell/taskpilot/git/refs' => Http::response([
            'message' => 'Reference already exists',
        ], 422),
    ]);

    $service = new ProjectGitHubIntegrationService($repository);

    expect(fn () => $service->createBranch($project, 'feature/taskpilot-github-branch'))
        ->toThrow(RuntimeException::class, 'Could not create GitHub branch');
});

it('commits and pushes a set of file changes to the github branch', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($connection);

    Http::fake([
        'https://api.github.com/repos/jagarcell/taskpilot/git/ref/heads/feature/taskpilot-github-branch' => Http::response([
            'ref' => 'refs/heads/feature/taskpilot-github-branch',
            'object' => ['sha' => 'branch-sha'],
        ], 200),
        'https://api.github.com/repos/jagarcell/taskpilot/git/commits/branch-sha' => Http::response([
            'sha' => 'branch-sha',
            'tree' => ['sha' => 'tree-sha'],
        ], 200),
        'https://api.github.com/repos/jagarcell/taskpilot/git/blobs' => Http::response([
            'sha' => 'blob-sha',
        ], 201),
        'https://api.github.com/repos/jagarcell/taskpilot/git/trees' => Http::response([
            'sha' => 'new-tree-sha',
        ], 201),
        'https://api.github.com/repos/jagarcell/taskpilot/git/commits' => Http::response([
            'sha' => 'new-commit-sha',
        ], 201),
        'https://api.github.com/repos/jagarcell/taskpilot/git/refs/heads/feature/taskpilot-github-branch' => Http::response([
            'ref' => 'refs/heads/feature/taskpilot-github-branch',
            'object' => ['sha' => 'new-commit-sha'],
        ], 200),
    ]);

    $service = new ProjectGitHubIntegrationService($repository);

    expect($service->commitAndPush($project, 'feature/taskpilot-github-branch', [
        'README.md' => "# TaskPilot\n",
    ], 'docs: add taskpilot description'))->toMatchArray([
        'branch_name' => 'feature/taskpilot-github-branch',
        'commit_sha' => 'new-commit-sha',
        'pushed' => true,
    ]);
});

it('throws a helpful exception when committing and pushing github changes fails', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($connection);

    Http::fake([
        'https://api.github.com/repos/jagarcell/taskpilot/git/ref/heads/feature/taskpilot-github-branch' => Http::response([
            'ref' => 'refs/heads/feature/taskpilot-github-branch',
            'object' => ['sha' => 'branch-sha'],
        ], 200),
        'https://api.github.com/repos/jagarcell/taskpilot/git/commits/branch-sha' => Http::response([
            'sha' => 'branch-sha',
            'tree' => ['sha' => 'tree-sha'],
        ], 200),
        'https://api.github.com/repos/jagarcell/taskpilot/git/blobs' => Http::response([
            'message' => 'Bad credentials',
        ], 401),
    ]);

    $service = new ProjectGitHubIntegrationService($repository);

    expect(fn () => $service->commitAndPush($project, 'feature/taskpilot-github-branch', [
        'README.md' => "# TaskPilot\n",
    ], 'docs: add taskpilot description'))
        ->toThrow(RuntimeException::class, 'Could not commit and push GitHub changes');
});

it('creates a pull request from the configured branch to the default branch', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($connection);

    Http::fake([
        'https://api.github.com/repos/jagarcell/taskpilot/pulls' => Http::response([
            'html_url' => 'https://github.com/jagarcell/taskpilot/pull/42',
            'number' => 42,
            'title' => 'docs: add taskpilot description',
            'body' => 'Summary of the change.',
            'head' => ['ref' => 'feature/taskpilot-github-branch'],
            'base' => ['ref' => 'main'],
            'state' => 'open',
        ], 201),
    ]);

    $service = new ProjectGitHubIntegrationService($repository);

    expect($service->createPullRequest($project, 'feature/taskpilot-github-branch', 'docs: add taskpilot description', 'Summary of the change.'))->toMatchArray([
        'owner' => 'jagarcell',
        'repo' => 'taskpilot',
        'number' => 42,
        'title' => 'docs: add taskpilot description',
        'url' => 'https://github.com/jagarcell/taskpilot/pull/42',
        'state' => 'open',
    ]);
});

it('throws a helpful exception when creating a github pull request fails', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($connection);

    Http::fake([
        'https://api.github.com/repos/jagarcell/taskpilot/pulls' => Http::response([
            'message' => 'Validation Failed',
        ], 422),
    ]);

    $service = new ProjectGitHubIntegrationService($repository);

    expect(fn () => $service->createPullRequest($project, 'feature/taskpilot-github-branch', 'docs: add taskpilot description', 'Summary of the change.'))
        ->toThrow(RuntimeException::class, 'Could not create GitHub pull request');
});

it('fetches the github pull request status and summarises its check results', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($connection);

    Http::fake([
        'https://api.github.com/repos/jagarcell/taskpilot/pulls/42' => Http::response([
            'number' => 42,
            'state' => 'open',
            'title' => 'docs: add taskpilot description',
            'html_url' => 'https://github.com/jagarcell/taskpilot/pull/42',
            'head' => ['sha' => 'abc123'],
            'base' => ['ref' => 'main'],
            'mergeable' => true,
        ], 200),
        'https://api.github.com/repos/jagarcell/taskpilot/commits/abc123/check-runs' => Http::response([
            'total_count' => 3,
            'check_runs' => [
                ['conclusion' => 'success'],
                ['conclusion' => 'failure'],
                ['status' => 'queued'],
            ],
        ], 200),
    ]);

    $service = new ProjectGitHubIntegrationService($repository);

    expect($service->getPullRequestStatus($project, 42))->toMatchArray([
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
            'total' => 3,
            'success' => 1,
            'failure' => 1,
            'pending' => 1,
            'skipped' => 0,
            'overall' => 'failure',
        ],
    ]);
});

it('throws a helpful exception when fetching the github pull request status fails', function () {
    $repository = Mockery::mock(ProjectGitHubRepositoryRepository::class);
    $project = Project::factory()->make(['id' => 10, 'owner_id' => 99]);
    $connection = ProjectGitHubRepository::factory()->make([
        'project_id' => $project->id,
        'github_owner' => 'jagarcell',
        'github_repo' => 'taskpilot',
        'default_branch' => 'main',
    ]);

    $repository->shouldReceive('findForProject')
        ->once()
        ->with($project)
        ->andReturn($connection);

    Http::fake([
        'https://api.github.com/repos/jagarcell/taskpilot/pulls/42' => Http::response([
            'message' => 'Not Found',
        ], 404),
    ]);

    $service = new ProjectGitHubIntegrationService($repository);

    expect(fn () => $service->getPullRequestStatus($project, 42))
        ->toThrow(RuntimeException::class, 'Could not fetch GitHub pull request status');
});
