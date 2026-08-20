<?php

use App\Models\Project;
use App\Models\User;

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
