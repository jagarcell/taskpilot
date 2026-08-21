<?php

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
