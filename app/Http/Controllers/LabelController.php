<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLabelRequest;
use App\Http\Requests\UpdateLabelRequest;
use App\Models\Label;
use App\Models\Project;
use App\Services\LabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LabelController extends Controller
{
    public function __construct(
        protected LabelService $labelService,
    ) {}

    /**
     * Store a new project label.
     *
     * @param  StoreLabelRequest  $request
     * @param  Project  $project
     * @return RedirectResponse
     * Logic: validate the label payload and persist it only when the current user owns the project.
     */
    public function store(StoreLabelRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();

        $this->labelService->createLabel($project, Auth::user(), $validated);

        return redirect()->route('projects.show', $project);
    }

    /**
     * Update an existing project label.
     *
     * @param  UpdateLabelRequest  $request
     * @param  Project  $project
     * @param  Label  $label
     * @return RedirectResponse
     * Logic: confirm the current user is the project owner and then update the label within that project's namespace.
     */
    public function update(UpdateLabelRequest $request, Project $project, Label $label): RedirectResponse
    {
        $validated = $request->validated();

        $this->labelService->updateLabel($project, $label, Auth::user(), $validated);

        return redirect()->route('projects.show', $project);
    }

    /**
     * Delete an existing project label.
     *
     * @param  Project  $project
     * @param  Label  $label
     * @return RedirectResponse
     * Logic: authorize project ownership and remove the label only when it belongs to the project.
     */
    public function destroy(Project $project, Label $label): RedirectResponse
    {
        $this->labelService->deleteLabel($project, $label, Auth::user());

        return redirect()->route('projects.show', $project);
    }
}
