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
