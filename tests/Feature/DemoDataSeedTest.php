<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the database seeder creates realistic demo project and workflow data', function () {
    $this->seed();

    $user = User::query()->where('email', 'jagarcell@gmail.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('jagarcell')
        ->and($user->projects()->count())->toBeGreaterThanOrEqual(1);

    $project = Project::query()->where('name', 'TaskPilot GitHub Preview')->first();

    expect($project)->not->toBeNull()
        ->and($project->githubRepository)->not->toBeNull()
        ->and($project->issues()->count())->toBeGreaterThanOrEqual(4);
});
