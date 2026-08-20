<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Notifications\MemberInvited;
use App\Repositories\ProjectMemberRepository;
use App\Services\ProjectMemberService;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

it('invites a user to the project and sends a notification', function () {
    Notification::fake();

    $repository = Mockery::mock(ProjectMemberRepository::class);
    $project = Project::factory()->make(['id' => 1, 'owner_id' => 10]);
    $inviter = User::factory()->make(['id' => 10]);
    $user = User::factory()->make(['id' => 20, 'email' => 'member@example.com']);
    $membership = ProjectMember::factory()->make([
        'id' => 99,
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => 'member',
    ]);

    $repository->shouldReceive('findUserByEmail')
        ->once()
        ->with('member@example.com')
        ->andReturn($user);

    $repository->shouldReceive('addMember')
        ->once()
        ->with($project, $user, 'member')
        ->andReturn($membership);

    $service = new ProjectMemberService($repository);

    $result = $service->inviteUser($project, $inviter, 'member@example.com', 'member');

    expect($result)->toBe($membership)
        ->and($result->role->value)->toBe('member');

    Notification::assertSentTo($user, MemberInvited::class);
});
