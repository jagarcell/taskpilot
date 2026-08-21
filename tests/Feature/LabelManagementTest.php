<?php

use App\Models\Label;
use App\Models\Project;
use App\Models\User;

it('project owners can create labels for a project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->post(route('projects.labels.store', $project), [
            'name' => 'frontend',
        ])
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseHas('labels', [
        'project_id' => $project->id,
        'name' => 'frontend',
    ]);
});

it('project members cannot create labels for a project', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $this->actingAs($member)
        ->post(route('projects.labels.store', $project), [
            'name' => 'backend',
        ])
        ->assertForbidden();
});

it('project owners can update a label', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $label = Label::factory()->create([
        'project_id' => $project->id,
        'name' => 'legacy',
    ]);

    $this->actingAs($owner)
        ->put(route('projects.labels.update', [$project, $label]), [
            'name' => 'frontend',
        ])
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseHas('labels', [
        'id' => $label->id,
        'name' => 'frontend',
    ]);
});

it('project owners can delete a label', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $label = Label::factory()->create([
        'project_id' => $project->id,
        'name' => 'qa',
    ]);

    $this->actingAs($owner)
        ->delete(route('projects.labels.destroy', [$project, $label]))
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseMissing('labels', [
        'id' => $label->id,
    ]);
});
