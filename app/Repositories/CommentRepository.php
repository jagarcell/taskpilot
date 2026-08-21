<?php

namespace App\Repositories;

use App\Models\Comment;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Collection;

class CommentRepository
{
    /**
     * Create a comment for an issue.
     *
     * @param  Issue  $issue
     * @param  array{body: string, user_id: int}  $attributes
     * @return Comment
     * Logic: persist the comment against the issue and return the saved record for redirect or response flows.
     */
    public function create(Issue $issue, array $attributes): Comment
    {
        $comment = $issue->comments()->create([
            'user_id' => $attributes['user_id'],
            'body' => trim((string) $attributes['body']),
        ]);

        return $comment->fresh();
    }

    /**
     * Return comments for an issue ordered newest first.
     *
     * @param  Issue  $issue
     * @return Collection<int, Comment>
     * Logic: fetch issue comments in a stable order so the UI can display them in chronological order.
     */
    public function listForIssue(Issue $issue): Collection
    {
        return $issue->comments()->with('user')->oldest()->get();
    }
}
