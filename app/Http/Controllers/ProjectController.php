<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectService $projectService,
    ) {}

    /**
     * Display the authenticated user's projects.
     *
     * @return Response
     * Logic: delegate the project lookup to the service layer and shape the list payload for the Inertia view.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $projects = $this->projectService->getProjectsForUser($user);

        return Inertia::render('projects/index', [
            'projects' => $projects->map(function (Project $project) use ($user) {
                $isOwner = $project->owner_id === $user->id;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'created_at' => $project->created_at?->toDateTimeString(),
                    'relationship' => $isOwner ? 'Owned' : 'Member',
                ];
            })->all(),
        ]);
    }

    /**
     * Display a specific project and its members.
     *
     * @param  Project  $project
     * @return Response
     * Logic: validate access through the service and then render the project detail payload for the member view.
     */
    public function show(Project $project): Response
    {
        $user = Auth::user();
        $project = $this->projectService->getProjectForUser($project, $user);

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'settings_summary' => 'Project settings',
                'members_label' => 'Members',
                'owner_label' => 'Owner',
                'owner' => [
                    'id' => $project->owner->id,
                    'name' => $project->owner->name,
                    'email' => $project->owner->email,
                ],
                'can_manage_project' => $project->owner_id === $user->id,
                'created_at' => $project->created_at?->toDateTimeString(),
            ],
            'members' => $project->members->map(fn ($member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role->value ?? $member->role,
            ])->all(),
            'issues' => $project->issues->map(fn ($issue) => [
                'id' => $issue->id,
                'issue_key' => $issue->issue_key,
                'title' => $issue->title,
                'description' => $issue->description,
                'type' => $issue->type->value ?? $issue->type,
                'status' => $issue->status->value ?? $issue->status,
                'priority' => $issue->priority->value ?? $issue->priority,
                'assignee_id' => $issue->assignee_id,
                'assignee_name' => $issue->assignee?->name,
            ])->all(),
        ]);
    }

    /**
     * Store a newly created project for the authenticated user.
     *
     * @param  StoreProjectRequest  $request
     * @return RedirectResponse
     * Logic: validate the project payload and delegate creation to the service layer while keeping the redirect logic here.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->projectService->createProject(Auth::user(), [
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
     * Logic: validate the updated fields and delegate ownership enforcement plus persistence to the service layer.
     */
    public function update(StoreProjectRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();

        $this->projectService->updateProject(Auth::user(), $project, [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('projects.show', $project);
    }
}
