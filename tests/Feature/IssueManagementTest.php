<?php

use App\Models\Agent;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('authenticated project owners can create an issue', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->post(route('projects.issues.store', $project), [
            'title' => 'Fix login redirect after sign in',
            'description' => 'Users land on the dashboard but the redirect target is incorrect.',
            'type' => 'bug',
            'priority' => 'high',
            'status' => 'todo',
            'assignee_id' => null,
        ])
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseHas('issues', [
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'title' => 'Fix login redirect after sign in',
        'type' => 'bug',
        'priority' => 'high',
        'status' => 'todo',
    ]);
});

it('project members can create issues for their project', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $this->actingAs($member)
        ->post(route('projects.issues.store', $project), [
            'title' => 'Add project summary widget',
            'description' => 'Summarize the latest activity in a compact dashboard card.',
            'type' => 'story',
            'priority' => 'medium',
            'status' => 'backlog',
        ])
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseHas('issues', [
        'project_id' => $project->id,
        'reporter_id' => $member->id,
        'title' => 'Add project summary widget',
    ]);
});

it('non-members cannot create issues for a project', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($stranger)
        ->post(route('projects.issues.store', $project), [
            'title' => 'Should not be allowed',
            'description' => 'This should be rejected.',
            'type' => 'task',
            'priority' => 'low',
            'status' => 'backlog',
        ])
        ->assertForbidden();
});

it('project members can update an issue', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $owner->id,
        'title' => 'Original issue title',
        'description' => 'Original issue description.',
        'type' => 'task',
        'priority' => 'medium',
        'status' => 'backlog',
    ]);

    $this->actingAs($member)
        ->put(route('projects.issues.update', [$project, $issue]), [
            'title' => 'Updated issue title',
            'description' => 'Updated issue description.',
            'type' => 'bug',
            'priority' => 'high',
            'status' => 'in_progress',
            'assignee_id' => $member->id,
        ])
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseHas('issues', [
        'id' => $issue->id,
        'title' => 'Updated issue title',
        'type' => 'bug',
        'priority' => 'high',
        'status' => 'in_progress',
        'assignee_id' => $member->id,
    ]);
});

it('changing an issue status records a workflow activity entry', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $owner->id,
        'title' => 'Status transition issue',
        'status' => 'todo',
    ]);

    $this->actingAs($member)
        ->put(route('projects.issues.update', [$project, $issue]), [
            'title' => 'Status transition issue',
            'description' => 'This issue should log its workflow change.',
            'type' => 'task',
            'priority' => 'medium',
            'status' => 'in_progress',
            'assignee_id' => $owner->id,
        ])
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseHas('issue_activities', [
        'issue_id' => $issue->id,
        'user_id' => $member->id,
        'type' => 'status_changed',
    ]);

    expect($issue->fresh()->status->value)->toBe('in_progress');
});

it('non-members cannot update an issue', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $owner->id,
    ]);

    $this->actingAs($stranger)
        ->put(route('projects.issues.update', [$project, $issue]), [
            'title' => 'Should not work',
            'description' => 'This update should be blocked.',
            'type' => 'story',
            'priority' => 'low',
            'status' => 'todo',
        ])
        ->assertForbidden();
});

it('project members can attach labels to an issue', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $bugLabel = Label::factory()->create([
        'project_id' => $project->id,
        'name' => 'bug',
    ]);
    $frontendLabel = Label::factory()->create([
        'project_id' => $project->id,
        'name' => 'frontend',
    ]);

    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $owner->id,
        'title' => 'Issue with labels',
    ]);

    $this->actingAs($member)
        ->put(route('projects.issues.update', [$project, $issue]), [
            'title' => 'Issue with labels',
            'description' => 'This issue should keep labels on update.',
            'type' => 'task',
            'priority' => 'medium',
            'status' => 'todo',
            'labels' => [$bugLabel->id, $frontendLabel->id],
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($issue->fresh()->labels()->pluck('labels.id')->all())->toBe([$bugLabel->id, $frontendLabel->id]);
});

it('project members can add comments to an issue', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $owner->id,
        'title' => 'Issue with comments',
    ]);

    $this->actingAs($member)
        ->post(route('projects.issues.comments.store', [$project, $issue]), [
            'body' => 'I have reviewed the issue and the fix is ready.',
        ])
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseHas('comments', [
        'issue_id' => $issue->id,
        'user_id' => $member->id,
        'body' => 'I have reviewed the issue and the fix is ready.',
    ]);
});

it('non-members cannot add comments to an issue', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $owner->id,
        'title' => 'Issue off-limits',
    ]);

    $this->actingAs($stranger)
        ->post(route('projects.issues.comments.store', [$project, $issue]), [
            'body' => 'This should not be allowed.',
        ])
        ->assertForbidden();
});

it('project members can view workflow columns for a kanban board', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $member->id,
        'title' => 'Board ready issue',
        'status' => 'todo',
    ]);

    $this->actingAs($member)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('projects/show')
            ->where('project.workflow_states.0.value', 'backlog')
            ->where('project.workflow_states.1.value', 'todo')
            ->where('issues_by_status.todo.0.id', $issue->id));
});

it('project members can view an issue detail page', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $member->id,
        'title' => 'Issue detail view',
    ]);

    $this->actingAs($member)
        ->get(route('projects.issues.show', [$project, $issue]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('issues/show')
            ->where('issue.id', $issue->id)
            ->where('issue.title', 'Issue detail view'));
});

it('non-members cannot view an issue detail page', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $owner->id,
        'title' => 'Restricted issue',
    ]);

    $this->actingAs($stranger)
        ->get(route('projects.issues.show', [$project, $issue]))
        ->assertForbidden();
});

it('creating an issue records an initial activity entry', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->post(route('projects.issues.store', $project), [
            'title' => 'Activity should be recorded',
            'description' => 'This issue should create activity history.',
            'type' => 'task',
            'priority' => 'medium',
            'status' => 'todo',
            'assignee_id' => null,
        ])
        ->assertRedirect(route('projects.show', $project));

    $issue = $project->issues()->first();

    $this->assertDatabaseHas('issue_activities', [
        'issue_id' => $issue->id,
        'type' => 'issue_created',
        'user_id' => $owner->id,
    ]);
});

it('project members can create an agent run for an issue', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $agent = Agent::factory()->create();
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $member->id,
        'title' => 'Issue with agent run',
    ]);

    $this->actingAs($member)
        ->post(route('projects.issues.agent-runs.store', [$project, $issue]), [
            'agent_id' => $agent->id,
            'model' => 'gpt-4o-mini',
            'provider' => 'openai',
            'input' => ['prompt' => 'Summarize this issue.'],
        ])
        ->assertRedirect(route('projects.issues.show', [$project, $issue]));

    $this->assertDatabaseHas('agent_runs', [
        'issue_id' => $issue->id,
        'agent_id' => $agent->id,
        'user_id' => $member->id,
        'status' => 'pending',
        'model' => 'gpt-4o-mini',
    ]);
});

it('issue detail pages include agent execution history', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $agent = Agent::factory()->create();
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $member->id,
        'title' => 'Issue with historical runs',
    ]);
    $issue->runs()->create([
        'agent_id' => $agent->id,
        'user_id' => $member->id,
        'model' => 'gpt-4o-mini',
        'provider' => 'openai',
        'status' => 'completed',
        'input' => ['prompt' => 'Summarize the issue.'],
        'output' => ['summary' => 'This issue is ready.'],
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $issue->runs()->first()->messages()->create([
        'role' => 'assistant',
        'content' => 'This issue is ready.',
    ]);

    $this->actingAs($member)
        ->get(route('projects.issues.show', [$project, $issue]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('issues/show')
            ->where('issue.runs.0.agent.name', $agent->name)
            ->where('issue.runs.0.status', 'completed')
            ->where('issue.runs.0.output.summary', 'This issue is ready.')
            ->where('issue.runs.0.messages.0.content', 'This issue is ready.'));
});

it('issue detail pages expose active agents for manual run requests', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $agent = Agent::factory()->create([
        'name' => 'Issue Analyzer',
        'is_active' => true,
    ]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $member->id,
        'title' => 'Issue with agent trigger',
    ]);

    $this->actingAs($member)
        ->get(route('projects.issues.show', [$project, $issue]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('issues/show')
            ->where('issue.agents.0.name', $agent->name));
});

it('non-members cannot create an agent run for an issue', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $agent = Agent::factory()->create();
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $owner->id,
        'title' => 'Restricted agent run',
    ]);

    $this->actingAs($stranger)
        ->post(route('projects.issues.agent-runs.store', [$project, $issue]), [
            'agent_id' => $agent->id,
            'model' => 'gpt-4o-mini',
            'provider' => 'openai',
            'input' => ['prompt' => 'Not allowed.'],
        ])
        ->assertForbidden();
});

it('project members can delete an issue', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $owner->id,
        'title' => 'Delete me',
    ]);

    $this->actingAs($member)
        ->delete(route('projects.issues.destroy', [$project, $issue]))
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseMissing('issues', [
        'id' => $issue->id,
    ]);
});

it('non-members cannot delete an issue', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $owner->id,
        'assignee_id' => $owner->id,
    ]);

    $this->actingAs($stranger)
        ->delete(route('projects.issues.destroy', [$project, $issue]))
        ->assertForbidden();
});
