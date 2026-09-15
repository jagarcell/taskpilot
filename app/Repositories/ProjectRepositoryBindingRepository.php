<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\ProjectRepositoryBinding;
use Illuminate\Support\Facades\Log;

class ProjectRepositoryBindingRepository
{
    /**
     * Persist the project's repository binding metadata.
     *
     * @param  Project  $project
     * @param  array{provider?: string|null, binding_type?: string|null, remote_owner?: string|null, remote_repo?: string|null, remote_url?: string|null, local_path?: string|null, default_branch?: string|null, is_active?: bool|null, verified_at?: string|null}  $attributes
     * @return ProjectRepositoryBinding
     * Logic: normalize the repository binding into a single project-scoped record so each workflow can resolve the same default code context from one source of truth.
     */
    public function bind(Project $project, array $attributes): ProjectRepositoryBinding
    {
        $provider = trim((string) ($attributes['provider'] ?? 'github')) ?: 'github';
        $bindingType = trim((string) ($attributes['binding_type'] ?? 'remote')) ?: 'remote';
        $remoteOwner = trim((string) ($attributes['remote_owner'] ?? ''));
        $remoteRepo = trim((string) ($attributes['remote_repo'] ?? ''));
        $localPath = trim((string) ($attributes['local_path'] ?? ''));
        $defaultBranch = trim((string) ($attributes['default_branch'] ?? 'main')) ?: 'main';
        $remoteUrl = trim((string) ($attributes['remote_url'] ?? ''));

        Log::info('Project repository binding repository save started.', [
            'project_id' => $project->id,
            'provider' => $provider,
            'binding_type' => $bindingType,
            'remote_owner' => $remoteOwner,
            'remote_repo' => $remoteRepo,
            'remote_url' => $remoteUrl,
            'local_path' => $localPath,
            'default_branch' => $defaultBranch,
        ]);

        if ($remoteUrl === '' && $remoteOwner !== '' && $remoteRepo !== '') {
            $remoteUrl = sprintf('https://github.com/%s/%s', $remoteOwner, $remoteRepo);
        }

        $payload = [
            'provider' => $provider,
            'binding_type' => $bindingType,
            'remote_owner' => $remoteOwner,
            'remote_repo' => $remoteRepo,
            'remote_url' => $remoteUrl,
            'local_path' => $localPath,
            'default_branch' => $defaultBranch,
            'is_active' => $attributes['is_active'] ?? true,
            'verified_at' => $attributes['verified_at'] ?? null,
        ];

        $binding = $project->repositoryBinding()->updateOrCreate(
            ['project_id' => $project->id],
            $payload,
        );

        Log::info('Project repository binding repository save finished.', [
            'project_id' => $project->id,
            'binding_id' => $binding->id,
            'provider' => $binding->provider,
            'binding_type' => $binding->binding_type,
            'remote_owner' => $binding->remote_owner,
            'remote_repo' => $binding->remote_repo,
            'remote_url' => $binding->remote_url,
        ]);

        return $binding;
    }

    /**
     * Fetch the configured repository binding for a project.
     *
     * @param  Project  $project
     * @return ProjectRepositoryBinding|null
     * Logic: return the stored binding so project workflows can validate, inspect, or display the active repository context without reaching into the database directly.
     */
    public function findForProject(Project $project): ?ProjectRepositoryBinding
    {
        if ($project->relationLoaded('repositoryBinding')) {
            return $project->getRelation('repositoryBinding');
        }

        return $project->repositoryBinding()->first();
    }
}
