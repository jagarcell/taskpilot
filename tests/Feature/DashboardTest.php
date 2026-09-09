<?php

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProviderOAuthCredential;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard reports the authenticated users active project count including invited projects', function () {
    $user = User::factory()->create();
    $ownedProject = Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    $invitedProject = Project::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $invitedProject->id,
        'user_id' => $user->id,
        'role' => 'member',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('active_projects_count', 2));

    $this->assertDatabaseHas('projects', ['id' => $ownedProject->id]);
    $this->assertDatabaseHas('project_members', ['project_id' => $invitedProject->id, 'user_id' => $user->id]);
});

test('the dashboard reports open issues for the authenticated user as reporter or assignee', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Issue::factory()->count(2)->create([
        'project_id' => $project->id,
        'reporter_id' => $user->id,
        'assignee_id' => $user->id,
        'status' => IssueStatus::TODO->value,
    ]);

    $otherUser = User::factory()->create();
    Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $otherUser->id,
        'assignee_id' => $otherUser->id,
        'status' => IssueStatus::TODO->value,
    ]);

    Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $user->id,
        'assignee_id' => $otherUser->id,
        'status' => IssueStatus::DONE->value,
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('open_issues_count', 2));
});

test('the dashboard reports ready-for-review issues for the authenticated user as reporter or assignee', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Issue::factory()->count(2)->create([
        'project_id' => $project->id,
        'reporter_id' => $user->id,
        'assignee_id' => $user->id,
        'status' => IssueStatus::REVIEW->value,
    ]);

    $otherUser = User::factory()->create();
    Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $otherUser->id,
        'assignee_id' => $otherUser->id,
        'status' => IssueStatus::REVIEW->value,
    ]);

    Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $user->id,
        'assignee_id' => $otherUser->id,
        'status' => IssueStatus::TODO->value,
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ready_for_review_count', 2));
});

test('the dashboard includes the authenticated users open issues with issue detail links', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $user->id,
        'assignee_id' => $user->id,
        'status' => IssueStatus::TODO->value,
        'title' => 'Fix the dashboard issue list',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('open_issues.0.id', $issue->id)
            ->where('open_issues.0.project_id', $project->id)
            ->where('open_issues.0.title', 'Fix the dashboard issue list')
            ->where('open_issues.0.detail_url', route('projects.issues.show', ['project' => $project->id, 'issue' => $issue->id])));
});

test('the dashboard includes the authenticated users ready-for-review issues with issue detail links', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $issue = Issue::factory()->create([
        'project_id' => $project->id,
        'reporter_id' => $user->id,
        'assignee_id' => $user->id,
        'status' => IssueStatus::REVIEW->value,
        'title' => 'Review the dashboard issue cards',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ready_for_review_issues.0.id', $issue->id)
            ->where('ready_for_review_issues.0.project_id', $project->id)
            ->where('ready_for_review_issues.0.title', 'Review the dashboard issue cards')
            ->where('ready_for_review_issues.0.detail_url', route('projects.issues.show', ['project' => $project->id, 'issue' => $issue->id])));
});

test('authenticated users can save OAuth credentials for a provider', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('dashboard.provider.oauth-credentials.store'), [
        'provider' => 'github',
        'client_id' => 'github-client-id',
        'client_secret' => 'super-secret-value-123',
        'redirect_uri' => 'https://example.test/auth/github/callback',
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('provider_oauth_credentials', [
        'user_id' => $user->id,
        'provider' => 'github',
        'client_id' => 'github-client-id',
        'client_secret' => 'super-secret-value-123',
        'redirect_uri' => 'https://example.test/auth/github/callback',
    ]);
});

test('existing OAuth credentials are masked in the dashboard response', function () {
    $user = User::factory()->create();
    ProviderOAuthCredential::factory()->create([
        'user_id' => $user->id,
        'provider' => 'github',
        'client_id' => 'github-client-id',
        'client_secret' => 'super-secret-value-123',
        'redirect_uri' => 'https://example.test/auth/github/callback',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $maskedSecret = str_repeat('*', strlen('super-secret-value-123') - 8).substr('super-secret-value-123', -8);

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('provider_oauth_credentials.github.client_id', 'github-client-id')
        ->where('provider_oauth_credentials.github.client_secret', $maskedSecret)
        ->where('provider_oauth_credentials.github.redirect_uri', 'https://example.test/auth/github/callback'));
});