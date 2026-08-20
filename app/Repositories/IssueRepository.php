<?php

namespace App\Repositories;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

class IssueRepository
{
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

        return $project->issues()->create($attributes);
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
