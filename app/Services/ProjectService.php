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
        $isOwner = $project->owner_id === $user->id;
        $isMember = $project->members()->where('user_id', $user->id)->exists();

        if (! $isOwner && ! $isMember) {
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
