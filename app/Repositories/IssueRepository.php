<?php

namespace App\Repositories;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\IssueActivity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

class IssueRepository
{
    /**
     * Determine whether a user can access a project and its issues.
     *
     * @param  Project  $project
     * @param  User  $user
     * @return bool
     * Logic: centralize the project-membership check in the repository so the service layer does not issue direct queries.
     */
    public function userHasAccessToProject(Project $project, User $user): bool
    {
        return $project->owner_id === $user->id || $project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether an assignee is valid for the project.
     *
     * @param  Project  $project
     * @param  int  $assigneeId
     * @return bool
     * Logic: ensure the assignee is either the project owner or a member before validating issue assignment updates.
     */
    public function assigneeIsValidForProject(Project $project, int $assigneeId): bool
    {
        return $project->owner_id === $assigneeId || $project->members()->where('user_id', $assigneeId)->exists();
    }

    /**
     * Create a new issue for a project.
     *
     * @param  Project  $project
     * @param  array<string, mixed>  $attributes
     * @return Issue
     * Logic: generate a project-scoped issue key before persisting the issue record.
     */
    public function create(Project $project, array $attributes): Issue
    {
        $attributes['issue_key'] = $this->generateIssueKey($project);

        $labels = $attributes['labels'] ?? [];
        unset($attributes['labels']);

        $issue = $project->issues()->create($attributes);

        if (! empty($labels)) {
            $issue->labels()->sync($labels);
        }

        return $issue->fresh();
    }

    /**
     * Update an issue record in place.
     *
     * @param  Issue  $issue
     * @param  array<string, mixed>  $attributes
     * @return Issue
     * Logic: persist the validated issue fields and refresh the instance so callers receive the updated record.
     */
    public function update(Issue $issue, array $attributes): Issue
    {
        $labels = $attributes['labels'] ?? null;
        unset($attributes['labels']);

        $issue->update($attributes);

        if ($labels !== null) {
            $issue->labels()->sync($labels);
        }

        return $issue->fresh()->load('labels');
    }

    /**
     * Fetch an issue for the project detail page with all nested relationships loaded.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @return Issue
     * Logic: return the issue model with project, assignee, reporter, labels, comments, and activity relations loaded for the detail view.
     */
    public function getIssueForProject(Project $project, Issue $issue): Issue
    {
        return $issue->load([
            'project.owner',
            'reporter',
            'assignee',
            'labels',
            'comments.user',
            'activities.user',
            'runs.agent',
        ]);
    }

    /**
     * Record a lifecycle event against the issue.
     *
     * @param  Issue  $issue
     * @param  string  $type
     * @param  int|null  $userId
     * @param  array<string, mixed>|null  $context
     * @return IssueActivity
     * Logic: track the user-triggered change in a reusable issue activity table so the detail page can render a chronological history.
     */
    public function recordActivity(Issue $issue, string $type, ?int $userId = null, ?array $context = null): IssueActivity
    {
        $messages = [
            'issue_created' => 'Issue created',
            'issue_updated' => 'Issue updated',
            'issue_deleted' => 'Issue deleted',
            'comment_added' => 'Comment added',
            'status_changed' => 'Status changed',
        ];

        return $issue->activities()->create([
            'user_id' => $userId,
            'type' => $type,
            'message' => $messages[$type] ?? 'Issue updated',
            'context' => $context ?? [],
        ]);
    }

    /**
     * Delete an issue record.
     *
     * @param  Issue  $issue
     * @return void
     * Logic: remove the issue record after authorization has been validated by the service layer.
     */
    public function delete(Issue $issue): void
    {
        $issue->delete();
    }

    /**
     * List open issues where the user is the reporter or the assignee.
     *
     * @param  User  $user
     * @return \Illuminate\Database\Eloquent\Collection<int, Issue>
     * Logic: return the active issues the user owns or is assigned to so the dashboard can render a concise list with direct links to detail pages.
     */
    public function listOpenIssuesForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Issue::query()
            ->with(['project', 'reporter', 'assignee'])
            ->where(function ($query) use ($user) {
                $query->where('reporter_id', $user->id)
                    ->orWhere('assignee_id', $user->id);
            })
            ->whereNotIn('status', [IssueStatus::DONE->value])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Count open issues where the user is the reporter or the assignee.
     *
     * @param  User  $user
     * @return int
     * Logic: count active issues matching the current user’s participation without mixing in issues they only observe or own indirectly.
     */
    public function countOpenIssuesForUser(User $user): int
    {
        return Issue::query()
            ->where(function ($query) use ($user) {
                $query->where('reporter_id', $user->id)
                    ->orWhere('assignee_id', $user->id);
            })
            ->whereNotIn('status', [IssueStatus::DONE->value])
            ->count();
    }

    /**
     * List issues ready for review where the user is the reporter or the assignee.
     *
     * @param  User  $user
     * @return \Illuminate\Database\Eloquent\Collection<int, Issue>
     * Logic: return the issues currently in review for the user so the dashboard can show a focused list with links into the detail view.
     */
    public function listReadyForReviewIssuesForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Issue::query()
            ->with(['project', 'reporter', 'assignee'])
            ->where(function ($query) use ($user) {
                $query->where('reporter_id', $user->id)
                    ->orWhere('assignee_id', $user->id);
            })
            ->where('status', IssueStatus::REVIEW->value)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Count issues ready for review where the user is the reporter or the assignee.
     *
     * @param  User  $user
     * @return int
     * Logic: count issues currently in the review state for the user without including unrelated backlog or done work.
     */
    public function countReadyForReviewIssuesForUser(User $user): int
    {
        return Issue::query()
            ->where(function ($query) use ($user) {
                $query->where('reporter_id', $user->id)
                    ->orWhere('assignee_id', $user->id);
            })
            ->where('status', IssueStatus::REVIEW->value)
            ->count();
    }

    /**
     * Generate a unique issue identifier for the project.
     *
     * @param  Project  $project
     * @return string
     * Logic: derive a unique, project-scoped key before insert so the database can accept the record without a default.
     */
    public function generateIssueKey(Project $project): string
    {
        $projectPrefix = strtoupper(Str::slug($project->name ?: 'PRJ', '-')) ?: 'PRJ';

        return sprintf('%s-%s', $projectPrefix, (string) Str::ulid());
    }
}
