<?php

use App\Models\User;

test('api health endpoint is reachable', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk()
        ->assertJson(['status' => 'ok']);
});

test('authenticated user can fetch their api profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email);
});

test('guests cannot access the authenticated api profile', function () {
    $this->getJson('/api/user')
        ->assertUnauthorized();
});
