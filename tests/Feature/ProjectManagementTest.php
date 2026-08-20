<?php

use App\Models\Project;
use App\Models\User;

test('guests are redirected when visiting the projects page', function () {
    $response = $this->get(route('projects.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view their projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk();
});

test('authenticated users can create a project', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'name' => 'Product Launch',
            'description' => 'Ship the new release plan.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'name' => 'Product Launch',
        'owner_id' => $user->id,
    ]);
});

test('project owners can add members to a project', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->post(route('projects.members.store', $project), [
            'email' => $member->email,
            'role' => 'member',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $member->id,
    ]);
});

test('non-owners cannot add members to a project', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($otherUser)
        ->post(route('projects.members.store', $project), [
            'email' => $member->email,
            'role' => 'member',
        ])
        ->assertForbidden();
});

test('project owners can view project details and members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee($project->name)
        ->assertSee($member->name);
});

test('project owners can view the project settings summary', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Project settings')
        ->assertSee('Members')
        ->assertSee('Owner');
});

test('project owners can update a project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->put(route('projects.update', $project), [
            'name' => 'Updated launch plan',
            'description' => 'Refined scope and timeline.',
        ])
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Updated launch plan',
    ]);
});

test('non-owners cannot update a project', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($otherUser)
        ->put(route('projects.update', $project), [
            'name' => 'Hacked project',
            'description' => 'Should not work.',
        ])
        ->assertForbidden();
});

test('project owners can update a member role', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $projectMember = $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $this->actingAs($owner)
        ->put(route('projects.members.update', [$project, $projectMember]), [
            'role' => 'owner',
        ])
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseHas('project_members', [
        'id' => $projectMember->id,
        'role' => 'owner',
    ]);
});

test('non-owners cannot update a member role', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $projectMember = $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $this->actingAs($otherUser)
        ->put(route('projects.members.update', [$project, $projectMember]), [
            'role' => 'owner',
        ])
        ->assertForbidden();
});

test('project owners can remove a member from a project', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $projectMember = $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $this->actingAs($owner)
        ->delete(route('projects.members.destroy', [$project, $projectMember]))
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseMissing('project_members', [
        'id' => $projectMember->id,
    ]);
});

test('non-owners cannot remove a member from a project', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $projectMember = $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $this->actingAs($otherUser)
        ->delete(route('projects.members.destroy', [$project, $projectMember]))
        ->assertForbidden();
});

test('project owners can transfer ownership to another member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $projectMember = $project->members()->create([
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $this->actingAs($owner)
        ->put(route('projects.members.update', [$project, $projectMember]), [
            'role' => 'owner',
        ])
        ->assertRedirect(route('projects.show', $project));

    $project->refresh();

    expect($project->owner_id)->toBe($member->id)
        ->and($project->members()->where('user_id', $member->id)->first()->role->value)->toBe('owner');
});

test('project owners cannot demote their own project membership', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $projectMember = $project->members()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $this->actingAs($owner)
        ->put(route('projects.members.update', [$project, $projectMember]), [
            'role' => 'member',
        ])
        ->assertForbidden();
});

test('project owners cannot remove their own project membership', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $projectMember = $project->members()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $this->actingAs($owner)
        ->delete(route('projects.members.destroy', [$project, $projectMember]))
        ->assertForbidden();
});
