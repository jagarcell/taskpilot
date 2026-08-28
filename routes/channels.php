<?php

use App\Models\Issue;
use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('project.{projectId}.issue.{issueId}.agent-runs', function ($user, int $projectId, int $issueId) {
    $project = Project::query()->find($projectId);
    $issue = Issue::query()->find($issueId);

    if (! $project || ! $issue) {
        return false;
    }

    if ($project->id !== $issue->project_id) {
        return false;
    }

    return $project->owner_id === $user->id || $project->members()->where('user_id', $user->id)->exists();
});
