<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRepositoryBindingRequest;
use App\Models\Project;
use App\Services\ProjectRepositoryBindingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class ProjectRepositoryBindingController extends Controller
{
    public function __construct(
        protected ProjectRepositoryBindingService $projectRepositoryBindingService,
    ) {}

    /**
     * Persist or update the project repository binding for the authenticated user.
     *
     * @param  Request  $request
     * @param  Project  $project
     * @return \Illuminate\Http\RedirectResponse
     * Logic: enforce project membership before saving the repository context and delegate persistence to the service layer so the binding stays canonical and project-scoped.
     */
    public function store(StoreProjectRepositoryBindingRequest $request, Project $project)
    {
        $validated = $request->validated();

        Log::info('Project repository binding submission received.', [
            'project_id' => $project->id,
            'binding_type' => $validated['binding_type'] ?? 'remote',
            'remote_owner' => $validated['remote_owner'] ?? null,
            'remote_repo' => $validated['remote_repo'] ?? null,
            'local_path' => $validated['local_path'] ?? null,
        ]);

        $binding = $this->projectRepositoryBindingService->bind($project, [
            'provider' => $validated['provider'] ?? 'github',
            'binding_type' => $validated['binding_type'] ?? 'remote',
            'remote_owner' => $validated['remote_owner'] ?? null,
            'remote_repo' => $validated['remote_repo'] ?? null,
            'remote_url' => $validated['remote_url'] ?? null,
            'local_path' => $validated['local_path'] ?? null,
            'default_branch' => $validated['default_branch'] ?? 'main',
            'is_active' => $validated['is_active'] ?? true,
            'verified_at' => now()->toDateTimeString(),
        ]);

        Log::info('Project repository binding persisted successfully.', [
            'project_id' => $project->id,
            'provider' => $binding->provider,
            'binding_type' => $binding->binding_type,
            'remote_owner' => $binding->remote_owner,
            'remote_repo' => $binding->remote_repo,
            'local_path' => $binding->local_path,
            'default_branch' => $binding->default_branch,
        ]);

        return Redirect::route('projects.show', $project);
    }

    /**
     * Update the project repository binding for the authenticated user.
     *
     * @param  Request  $request
     * @param  Project  $project
     * @return \Illuminate\Http\RedirectResponse
     * Logic: reuse the same authorization and validation flow as store so project repository context remains consistent across updates.
     */
    public function update(StoreProjectRepositoryBindingRequest $request, Project $project)
    {
        return $this->store($request, $project);
    }
}
