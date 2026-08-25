<?php

use App\Models\Agent;
use App\Models\User;

test('authenticated users can create an agent definition', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('agents.store'), [
            'name' => 'Issue Analyzer',
            'description' => 'Analyzes issue details and suggests next steps.',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'is_active' => true,
        ])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('agents', [
        'name' => 'Issue Analyzer',
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'is_active' => true,
    ]);
});

test('authenticated users can activate and deactivate an agent', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'name' => 'Planning Agent',
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->put(route('agents.update', $agent), [
            'name' => 'Planning Agent',
            'description' => 'Generates implementation plans.',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'is_active' => true,
        ])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('agents', [
        'id' => $agent->id,
        'is_active' => true,
    ]);
});

test('unsupported agent providers are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('agents.store'), [
            'name' => 'Unsupported Agent',
            'description' => 'Should not be allowed.',
            'provider' => 'anthropic',
            'model' => 'claude-3-5-sonnet',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('provider');

    $this->assertDatabaseMissing('agents', [
        'name' => 'Unsupported Agent',
    ]);
});
