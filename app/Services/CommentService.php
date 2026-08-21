<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Repositories\CommentRepository;
use App\Repositories\IssueRepository;
use Illuminate\Database\Eloquent\Collection;

class CommentService
{
    public function __construct(
        protected CommentRepository $commentRepository,
        protected IssueRepository $issueRepository,
    ) {}

    /**
     * Create a comment on an issue for a user with project access.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @param  User  $user
     * @param  array{body: string}  $attributes
     * @return Comment
     * Logic: enforce project membership before persisting a comment and keep the issue route ownership consistent.
     */
    public function createComment(Project $project, Issue $issue, User $user, array $attributes): Comment
    {
        abort_unless($issue->project_id === $project->id, 404);
        abort_unless($this->issueRepository->userHasAccessToProject($project, $user), 403);

        return $this->commentRepository->create($issue, [
            'body' => $attributes['body'],
            'user_id' => $user->id,
        ]);
    }

    /**
     * Return comments for an issue after validating project access.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @param  User  $user
     * @return Collection<int, Comment>
     * Logic: ensure the viewer belongs to the project before exposing issue discussion data.
     */
    public function getCommentsForIssue(Project $project, Issue $issue, User $user): Collection
    {
        abort_unless($issue->project_id === $project->id, 404);
        abort_unless($this->issueRepository->userHasAccessToProject($project, $user), 403);

        return $this->commentRepository->listForIssue($issue);
    }
}
