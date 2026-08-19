<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display the authenticated user's projects.
     *
     * @return Response
     * Logic: load the current user's projects for the project index view.
     */
    public function index(): Response
    {
        $projects = Auth::user()->projects()->latest()->get();

        return Inertia::render('projects/index', [
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'created_at' => $project->created_at?->toDateTimeString(),
            ])->all(),
        ]);
    }

    /**
     * Display a specific project and its members.
     *
     * @param  Project  $project
     * @return Response
     * Logic: allow the owner to review the project details and current member roster.
     */
    public function show(Project $project): Response
    {
        abort_unless($project->owner_id === Auth::id(), 403);

        $project->load('members.user', 'owner');

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'owner' => [
                    'id' => $project->owner->id,
                    'name' => $project->owner->name,
                    'email' => $project->owner->email,
                ],
                'created_at' => $project->created_at?->toDateTimeString(),
            ],
            'members' => $project->members->map(fn ($member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role->value ?? $member->role,
            ])->all(),
        ]);
    }

    /**
     * Store a newly created project for the authenticated user.
     *
     * @param  StoreProjectRequest  $request
     * @return RedirectResponse
     * Logic: validate the project payload and persist it with the current user as owner.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Auth::user()->projects()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('projects.index');
    }

    /**
     * Update the details of an existing project.
     *
     * @param  StoreProjectRequest  $request
     * @param  Project  $project
     * @return RedirectResponse
     * Logic: validate the updated project fields and persist changes for the owning user only.
     */
    public function update(StoreProjectRequest $request, Project $project): RedirectResponse
    {
        abort_unless($project->owner_id === Auth::id(), 403);

        $validated = $request->validated();

        $project->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('projects.show', $project);
    }
}
