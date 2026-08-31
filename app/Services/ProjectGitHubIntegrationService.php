<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectGitHubRepository;
use App\Repositories\ProjectGitHubRepositoryRepository;

class ProjectGitHubIntegrationService
{
    public function __construct(
        protected ProjectGitHubRepositoryRepository $projectGitHubRepositoryRepository,
    ) {}

    /**
     * Save or update the GitHub repository connection for a project.
     *
     * @param  Project  $project
     * @param  array{github_owner: string, github_repo: string, default_branch?: string|null, repository_url?: string|null, is_active?: bool|null}  $attributes
     * @return ProjectGitHubRepository
     * Logic: delegate the persistence to the repository layer so the integration contract remains isolated from the rest of the application.
     */
    public function connect(Project $project, array $attributes): ProjectGitHubRepository
    {
        return $this->projectGitHubRepositoryRepository->connect($project, $attributes);
    }

    /**
     * Return the configured GitHub repository for a project.
     *
     * @param  Project  $project
     * @return ProjectGitHubRepository|null
     * Logic: retrieve the saved repository connection so branch and PR operations are based on the project's intended target repository.
     */
    public function getForProject(Project $project): ?ProjectGitHubRepository
    {
        return $this->projectGitHubRepositoryRepository->findForProject($project);
    }
}
