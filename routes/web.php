<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentRunController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GitHubOAuthController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProviderConnectionTestController;
use App\Http\Controllers\WorkflowRunController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/provider/test', ProviderConnectionTestController::class)->name('dashboard.provider.test');
    Route::post('/dashboard/provider/reauth', [ProviderConnectionTestController::class, 'reauth'])->name('dashboard.provider.reauth');
    Route::post('/dashboard/provider/oauth-credentials', [DashboardController::class, 'storeProviderOAuthCredentials'])->name('dashboard.provider.oauth-credentials.store');
    Route::get('/auth/github', [GitHubOAuthController::class, 'authorize'])->name('github.oauth.authorize');
    Route::get('/auth/github/callback', [GitHubOAuthController::class, 'callback'])->name('github.oauth.callback');

    Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
    Route::put('/agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
    Route::delete('/agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::get('/projects/{project}/issues/{issue}', [IssueController::class, 'show'])->name('projects.issues.show');
    Route::post('/projects/{project}/issues', [IssueController::class, 'store'])->name('projects.issues.store');
    Route::put('/projects/{project}/issues/{issue}', [IssueController::class, 'update'])->name('projects.issues.update');
    Route::delete('/projects/{project}/issues/{issue}', [IssueController::class, 'destroy'])->name('projects.issues.destroy');
    Route::post('/projects/{project}/issues/{issue}/comments', [CommentController::class, 'store'])->name('projects.issues.comments.store');
    Route::post('/projects/{project}/issues/{issue}/agent-runs', [AgentRunController::class, 'store'])->name('projects.issues.agent-runs.store');
    Route::post('/projects/{project}/issues/{issue}/workflow-runs/start', [WorkflowRunController::class, 'start'])->name('projects.issues.workflow-runs.start');
    Route::post('/projects/{project}/issues/{issue}/workflow-runs/{workflowRun}/approve', [WorkflowRunController::class, 'approve'])->name('projects.issues.workflow-runs.approve');
    Route::post('/projects/{project}/issues/{issue}/workflow-runs/{workflowRun}/retry', [WorkflowRunController::class, 'retry'])->name('projects.issues.workflow-runs.retry');

    Route::post('/projects/{project}/labels', [LabelController::class, 'store'])->name('projects.labels.store');
    Route::put('/projects/{project}/labels/{label}', [LabelController::class, 'update'])->name('projects.labels.update');
    Route::delete('/projects/{project}/labels/{label}', [LabelController::class, 'destroy'])->name('projects.labels.destroy');

    Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::put('/projects/{project}/members/{projectMember}', [ProjectMemberController::class, 'update'])->name('projects.members.update');
    Route::delete('/projects/{project}/members/{projectMember}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
});

require __DIR__.'/settings.php';
