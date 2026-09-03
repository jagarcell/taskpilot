<?php

namespace Database\Seeders;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectGitHubRepository;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AgentSeeder::class);

        $owner = User::firstOrCreate(
            ['email' => 'jagarcell@gmail.com'],
            [
                'name' => 'jagarcell',
                'password' => bcrypt('password123'),
            ],
        );

        $project = Project::firstOrCreate(
            ['name' => 'TaskPilot GitHub Preview'],
            [
                'owner_id' => $owner->id,
                'description' => 'Demo project for validating the GitHub pull-request and workflow preview experience.',
            ],
        );

        $project->members()->firstOrCreate(
            ['user_id' => $owner->id],
            ['role' => 'owner'],
        );

        $project->members()->firstOrCreate(
            ['user_id' => $owner->id],
            ['role' => 'member'],
        );

        ProjectGitHubRepository::updateOrCreate(
            ['project_id' => $project->id],
            [
                'github_owner' => 'jagarcell',
                'github_repo' => 'test_taskpilot',
                'default_branch' => 'main',
                'repository_url' => 'https://github.com/jagarcell/test_taskpilot',
                'is_active' => true,
            ],
        );

        $issueSeed = [
            [
                'issue_key' => 'PRJ-1001',
                'title' => 'Preview the pull request workflow',
                'description' => 'This issue shows the GitHub PR card and workflow status panel in the issue detail page.',
                'type' => IssueType::TASK->value,
                'status' => IssueStatus::IN_PROGRESS->value,
                'priority' => IssuePriority::HIGH->value,
            ],
            [
                'issue_key' => 'PRJ-1002',
                'title' => 'Finalize the branch handoff',
                'description' => 'The feature branch is ready to be reviewed and shown in the PR development UI.',
                'type' => IssueType::STORY->value,
                'status' => IssueStatus::REVIEW->value,
                'priority' => IssuePriority::MEDIUM->value,
            ],
            [
                'issue_key' => 'PRJ-1003',
                'title' => 'Improve issue analysis summaries',
                'description' => 'The issue detail page should surface the latest AI analysis and planning summary in a more scannable layout.',
                'type' => IssueType::TASK->value,
                'status' => IssueStatus::TODO->value,
                'priority' => IssuePriority::MEDIUM->value,
            ],
            [
                'issue_key' => 'PRJ-1004',
                'title' => 'Ship approval-gated workflow transitions',
                'description' => 'The workflow engine should require approval before implementation and pull request operations begin.',
                'type' => IssueType::EPIC->value,
                'status' => IssueStatus::BACKLOG->value,
                'priority' => IssuePriority::URGENT->value,
            ],
            [
                'issue_key' => 'PRJ-1005',
                'title' => 'Stabilize realtime progress notifications',
                'description' => 'The client should update issue workflow state and agent progress in near real time without a refresh.',
                'type' => IssueType::BUG->value,
                'status' => IssueStatus::DONE->value,
                'priority' => IssuePriority::LOW->value,
            ],
        ];

        foreach ($issueSeed as $issueData) {
            $project->issues()->firstOrCreate(
                ['issue_key' => $issueData['issue_key']],
                [
                    'reporter_id' => $owner->id,
                    'assignee_id' => $owner->id,
                    'title' => $issueData['title'],
                    'description' => $issueData['description'],
                    'type' => $issueData['type'],
                    'status' => $issueData['status'],
                    'priority' => $issueData['priority'],
                ],
            );
        }
    }
}
