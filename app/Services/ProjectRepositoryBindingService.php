<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectRepositoryBinding;
use App\Repositories\ProjectRepositoryBindingRepository;

class ProjectRepositoryBindingService
{
    public function __construct(
        protected ProjectRepositoryBindingRepository $projectRepositoryBindingRepository,
    ) {}

    /**
     * Save or update the project repository binding.
     *
     * @param  Project  $project
     * @param  array{provider?: string|null, binding_type?: string|null, remote_owner?: string|null, remote_repo?: string|null, remote_url?: string|null, local_path?: string|null, default_branch?: string|null, is_active?: bool|null, verified_at?: string|null}  $attributes
     * @return ProjectRepositoryBinding
     * Logic: delegate persistence to the repository layer so the project can resolve one canonical binding record regardless of whether the connection is local or remote.
     */
    public function bind(Project $project, array $attributes): ProjectRepositoryBinding
    {
        return $this->projectRepositoryBindingRepository->bind($project, $attributes);
    }

    /**
     * Return the configured repository binding for the project.
     *
     * @param  Project  $project
     * @return ProjectRepositoryBinding|null
     * Logic: resolve the saved binding so later workflow steps can inspect the active repository without duplicating persistence rules in the service layer.
     */
    public function getForProject(Project $project): ?ProjectRepositoryBinding
    {
        return $this->projectRepositoryBindingRepository->findForProject($project);
    }
}
