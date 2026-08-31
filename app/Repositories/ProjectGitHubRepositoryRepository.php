<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\ProjectGitHubRepository;

class ProjectGitHubRepositoryRepository
{
    /**
     * Connect a project to a GitHub repository.
     *
     * @param  Project  $project
     * @param  array{github_owner: string, github_repo: string, default_branch?: string|null, repository_url?: string|null, is_active?: bool|null}  $attributes
     * @return ProjectGitHubRepository
     * Logic: persist the project's GitHub repository metadata and keep the stored path normalized so future branch operations can reuse one source of truth.
     */
    public function connect(Project $project, array $attributes): ProjectGitHubRepository
    {
        $githubOwner = trim((string) ($attributes['github_owner'] ?? ''));
        $githubRepo = trim((string) ($attributes['github_repo'] ?? ''));

        $payload = [
            'github_owner' => $githubOwner,
            'github_repo' => $githubRepo,
            'default_branch' => $attributes['default_branch'] ?? 'main',
            'repository_url' => $attributes['repository_url'] ?? sprintf('https://github.com/%s/%s', $githubOwner, $githubRepo),
            'is_active' => $attributes['is_active'] ?? true,
        ];

        $payload['repository_url'] = $payload['repository_url'] ?: sprintf('https://github.com/%s/%s', $githubOwner, $githubRepo);

        return $project->githubRepository()->updateOrCreate(
            ['project_id' => $project->id],
            $payload,
        );
    }

    /**
     * Fetch the configured GitHub repository connection for a project.
     *
     * @param  Project  $project
     * @return ProjectGitHubRepository|null
     * Logic: return the saved project repository record so the application can inspect, branch, and open pull requests against the same target repository.
     */
    public function findForProject(Project $project): ?ProjectGitHubRepository
    {
        return $project->githubRepository()->first();
    }
}
