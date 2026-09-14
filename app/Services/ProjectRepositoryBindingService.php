<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectRepositoryBinding;
use App\Repositories\ProjectRepositoryBindingRepository;
use RuntimeException;

class ProjectRepositoryBindingService
{
    public function __construct(
        protected ProjectRepositoryBindingRepository $projectRepositoryBindingRepository,
        protected ?ProjectGitHubIntegrationService $projectGitHubIntegrationService = null,
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

    /**
     * Validate the configured remote repository and persist verification metadata.
     *
     * @param  Project  $project
     * @return array{provider: string, binding_type: string, remote_owner: string, remote_repo: string, remote_url: string|null, default_branch: string|null, valid: bool, message?: string}
     * Logic: confirm the bound remote repository is valid and accessible before it can act as the project’s execution context, then update the binding with the verification timestamp.
     */
    public function validate(Project $project): array
    {
        $binding = $this->projectRepositoryBindingRepository->findForProject($project);

        if ($binding === null) {
            return [
                'provider' => 'github',
                'binding_type' => 'remote',
                'remote_owner' => '',
                'remote_repo' => '',
                'remote_url' => null,
                'default_branch' => null,
                'valid' => false,
                'message' => 'No repository binding is configured for this project.',
            ];
        }

        $provider = trim((string) ($binding->provider ?? 'github')) ?: 'github';
        $bindingType = trim((string) ($binding->binding_type ?? 'remote')) ?: 'remote';
        $remoteOwner = trim((string) ($binding->remote_owner ?? ''));
        $remoteRepo = trim((string) ($binding->remote_repo ?? ''));

        if ($provider !== 'github' || $bindingType !== 'remote' || $remoteOwner === '' || $remoteRepo === '') {
            return [
                'provider' => $provider,
                'binding_type' => $bindingType,
                'remote_owner' => $remoteOwner,
                'remote_repo' => $remoteRepo,
                'remote_url' => $binding->remote_url,
                'default_branch' => $binding->default_branch,
                'valid' => false,
                'message' => 'This project repository binding is missing a valid remote GitHub target.',
            ];
        }

        $integration = $this->projectGitHubIntegrationService ?? app(ProjectGitHubIntegrationService::class);

        try {
            $metadata = $integration->inspectRepository($project);
        } catch (RuntimeException $exception) {
            return [
                'provider' => $provider,
                'binding_type' => $bindingType,
                'remote_owner' => $remoteOwner,
                'remote_repo' => $remoteRepo,
                'remote_url' => $binding->remote_url,
                'default_branch' => $binding->default_branch,
                'valid' => false,
                'message' => $exception->getMessage(),
            ];
        }

        $verifiedAt = now()->toDateTimeString();
        $updatedBinding = $this->projectRepositoryBindingRepository->bind($project, [
            'provider' => $provider,
            'binding_type' => $bindingType,
            'remote_owner' => $remoteOwner,
            'remote_repo' => $remoteRepo,
            'remote_url' => $metadata['repository_url'] ?? $binding->remote_url,
            'default_branch' => $metadata['default_branch'] ?? $binding->default_branch ?? 'main',
            'is_active' => $binding->is_active ?? true,
            'verified_at' => $verifiedAt,
        ]);

        return [
            'provider' => $provider,
            'binding_type' => $bindingType,
            'remote_owner' => $updatedBinding->remote_owner,
            'remote_repo' => $updatedBinding->remote_repo,
            'remote_url' => $updatedBinding->remote_url,
            'default_branch' => $updatedBinding->default_branch,
            'valid' => true,
            'message' => 'Repository successfully validated.',
        ];
    }

    /**
     * Validate the configured local repository path and persist verification metadata.
     *
     * @param  Project  $project
     * @return array{provider: string, binding_type: string, local_path: string, valid: bool, message?: string}
     * Logic: confirm the project’s local path points to a real Git repository before it can act as the project’s default execution context, then store the validation timestamp.
     */
    public function validateLocal(Project $project): array
    {
        $binding = $this->projectRepositoryBindingRepository->findForProject($project);

        if ($binding === null) {
            return [
                'provider' => 'github',
                'binding_type' => 'local',
                'local_path' => '',
                'valid' => false,
                'message' => 'No repository binding is configured for this project.',
            ];
        }

        $provider = trim((string) ($binding->provider ?? 'github')) ?: 'github';
        $bindingType = trim((string) ($binding->binding_type ?? 'local')) ?: 'local';
        $localPath = trim((string) ($binding->local_path ?? ''));

        if ($bindingType !== 'local' || $localPath === '' || ! is_dir($localPath) || ! is_dir($localPath.'/.git')) {
            return [
                'provider' => $provider,
                'binding_type' => $bindingType,
                'local_path' => $localPath,
                'valid' => false,
                'message' => 'This project repository binding is missing a valid local Git repository path.',
            ];
        }

        $verifiedAt = now()->toDateTimeString();
        $updatedBinding = $this->projectRepositoryBindingRepository->bind($project, [
            'provider' => $provider,
            'binding_type' => $bindingType,
            'local_path' => $localPath,
            'is_active' => $binding->is_active ?? true,
            'verified_at' => $verifiedAt,
        ]);

        return [
            'provider' => $provider,
            'binding_type' => $bindingType,
            'local_path' => $updatedBinding->local_path,
            'valid' => true,
            'message' => 'Local repository successfully validated.',
        ];
    }
}
