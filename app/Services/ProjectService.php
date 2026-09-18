<?php

namespace App\Services;

use App\Enums\IssueStatus;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ProjectRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

class ProjectService
{
    public function __construct(
        protected ProjectRepository $projectRepository,
        protected ?ProjectGitHubIntegrationService $projectGitHubIntegrationService = null,
    ) {}

    /**
     * Return projects visible to the authenticated user.
     *
     * @param  User  $user
     * @return Collection<int, Project>
     * Logic: delegate project listing to the repository layer and keep the controller focused on response shaping.
     */
    public function getProjectsForUser(User $user): Collection
    {
        return $this->projectRepository->listForUser($user);
    }

    /**
     * Return a project once ownership or membership access has been validated.
     *
     * @param  Project  $project
     * @param  User  $user
     * @return Project
     * Logic: enforce access control before exposing a project detail payload and load the related membership data.
     */
    public function getProjectForUser(Project $project, User $user): Project
    {
        if (! $this->projectRepository->isOwnerOrMember($project, $user)) {
            throw new AuthorizationException('You do not have access to this project.');
        }

        return $this->projectRepository->getProjectWithRelations($project);
    }

    /**
     * Create a new project for the authenticated owner.
     *
     * @param  User  $user
     * @param  array{name: string, description?: string|null}  $attributes
     * @return Project
     * Logic: validate the owner identity and persist the project record using the repository layer.
     */
    public function createProject(User $user, array $attributes): Project
    {
        return $this->projectRepository->createForOwner($user, $attributes);
    }

    /**
     * Update a project if the user is the owner.
     *
     * @param  User  $user
     * @param  Project  $project
     * @param  array{name?: string, description?: string|null}  $attributes
     * @return Project
     * Logic: enforce ownership and delegate persistence to the repository so controller logic stays thin.
     */
    public function updateProject(User $user, Project $project, array $attributes): Project
    {
        if ($project->owner_id !== $user->id) {
            throw new AuthorizationException('Only the project owner can update this project.');
        }

        return $this->projectRepository->updateProject($project, $attributes);
    }

    /**
     * Build the project detail payload for the Inertia project show page.
     *
     * @param  Project  $project
     * @param  User  $user
     * @return array<string, mixed>
     * Logic: assemble the normalized project, member, issue, and workflow state payload that the frontend expects while keeping the controller focused on rendering.
     */
    public function getProjectDetailPayload(Project $project, User $user): array
    {
        $githubRepository = $project->githubRepository;
        $repositoryBinding = $project->repositoryBinding;
        $pullRequestStatus = null;

        if ($githubRepository !== null && $this->projectGitHubIntegrationService !== null) {
            try {
                $pullRequestStatus = $this->projectGitHubIntegrationService->getLatestOpenPullRequestStatus($project);
            } catch (\RuntimeException) {
                $pullRequestStatus = null;
            }
        }

        $defaultPullRequestStatus = [
            'owner' => $githubRepository?->github_owner ?? '',
            'repo' => $githubRepository?->github_repo ?? '',
            'number' => null,
            'state' => 'none',
            'title' => null,
            'url' => null,
            'head_sha' => null,
            'base_branch' => $githubRepository?->default_branch ?? 'main',
            'mergeable' => null,
            'checks' => [
                'total' => 0,
                'success' => 0,
                'failure' => 0,
                'pending' => 0,
                'skipped' => 0,
                'overall' => 'no_pull_request',
            ],
        ];

        $repositoryStatus = null;
        if ($repositoryBinding !== null) {
            $verifiedAtValue = $repositoryBinding->verified_at;
            $verifiedAtString = is_string($verifiedAtValue)
                ? $verifiedAtValue
                : ($verifiedAtValue instanceof \DateTimeInterface ? $verifiedAtValue->format('Y-m-d H:i:s') : $verifiedAtValue?->toDateTimeString());

            $oauthStatus = 'connected';
            $oauthMessage = 'Project GitHub OAuth is connected and private repositories can be validated.';

            if ($repositoryBinding->binding_type === 'remote' && ($repositoryBinding->provider ?? 'github') === 'github') {
                $token = app(\App\Repositories\RepositoryTokenRepository::class)->findLatestForProjectUser($project, $user, 'github');

                if ($token === null) {
                    $oauthStatus = 'missing';
                    $oauthMessage = 'Private repository access is not connected yet. Use GitHub OAuth to grant this project access to private repositories.';
                } elseif ($token->expires_at !== null && $token->expires_at->isPast()) {
                    $oauthStatus = 'expired';
                    $oauthMessage = 'The saved GitHub OAuth token for this project has expired. Reconnect GitHub OAuth to restore private repository access.';
                }
            }

            $repositoryStatus = [
                'provider' => $repositoryBinding->provider ?? 'github',
                'binding_type' => $repositoryBinding->binding_type ?? 'remote',
                'remote_owner' => $repositoryBinding->remote_owner ?? null,
                'remote_repo' => $repositoryBinding->remote_repo ?? null,
                'remote_url' => $repositoryBinding->remote_url ?? null,
                'local_path' => $repositoryBinding->local_path ?? null,
                'default_branch' => $repositoryBinding->default_branch ?? 'main',
                'is_active' => (bool) ($repositoryBinding->is_active ?? true),
                'verified_at' => $verifiedAtString,
                'status' => $verifiedAtString !== null && $verifiedAtString !== '' ? 'verified' : 'pending',
                'oauth_status' => $oauthStatus,
                'oauth_message' => $oauthMessage,
            ];
        }

        $issuePayload = $project->issues->map(fn ($issue) => [
            'id' => $issue->id,
            'issue_key' => $issue->issue_key,
            'title' => $issue->title,
            'description' => $issue->description,
            'type' => $issue->type->value ?? $issue->type,
            'status' => $issue->status->value ?? $issue->status,
            'priority' => $issue->priority->value ?? $issue->priority,
            'assignee_id' => $issue->assignee_id,
            'assignee_name' => $issue->assignee?->name,
            'labels' => $issue->labels->map(fn ($label) => [
                'id' => $label->id,
                'name' => $label->name,
            ])->all(),
            'comments' => $issue->comments->map(fn ($comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'user_name' => $comment->user?->name,
                'created_at' => $comment->created_at?->toDateTimeString(),
            ])->all(),
        ])->all();

        $workflowStates = collect(IssueStatus::cases())->map(fn (IssueStatus $status) => [
            'value' => $status->value,
            'label' => match ($status) {
                IssueStatus::BACKLOG => 'Backlog',
                IssueStatus::TODO => 'Todo',
                IssueStatus::IN_PROGRESS => 'In Progress',
                IssueStatus::REVIEW => 'Review',
                IssueStatus::DONE => 'Done',
            },
        ])->all();

        $issuesByStatus = [];
        foreach (IssueStatus::cases() as $status) {
            $issuesByStatus[$status->value] = collect($issuePayload)
                ->filter(fn ($issue) => ($issue['status'] ?? null) === $status->value)
                ->values()
                ->all();
        }

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'settings_summary' => 'Project settings',
                'members_label' => 'Members',
                'owner_label' => 'Owner',
                'owner' => [
                    'id' => $project->owner->id,
                    'name' => $project->owner->name,
                    'email' => $project->owner->email,
                ],
                'github' => $githubRepository !== null ? [
                    'owner' => $githubRepository->github_owner,
                    'repo' => $githubRepository->github_repo,
                    'default_branch' => $githubRepository->default_branch ?? 'main',
                    'repository_url' => $githubRepository->repository_url ?? sprintf('https://github.com/%s/%s', $githubRepository->github_owner, $githubRepository->github_repo),
                    'is_active' => (bool) $githubRepository->is_active,
                    'pull_request' => array_merge($defaultPullRequestStatus, $pullRequestStatus ?? []),
                ] : null,
                'repository' => $repositoryStatus,
                'can_manage_project' => $project->owner_id === $user->id,
                'created_at' => $project->created_at?->toDateTimeString(),
                'workflow_states' => $workflowStates,
            ],
            'members' => $project->members->map(fn ($member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role->value ?? $member->role,
            ])->all(),
            'labels' => $project->labels->map(fn ($label) => [
                'id' => $label->id,
                'name' => $label->name,
            ])->all(),
            'issues' => $issuePayload,
            'issues_by_status' => $issuesByStatus,
            'assignees' => collect([$project->owner, ...$project->members->map(fn ($member) => $member->user)->filter()])
                ->unique('id')
                ->values()
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->all(),
        ];
    }
}
