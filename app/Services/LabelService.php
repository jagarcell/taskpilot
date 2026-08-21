<?php

namespace App\Services;

use App\Models\Label;
use App\Models\Project;
use App\Models\User;
use App\Repositories\LabelRepository;
use Illuminate\Support\Facades\Auth;

class LabelService
{
    public function __construct(
        protected LabelRepository $labelRepository,
    ) {}

    /**
     * Create a label for a project if the current user owns the project.
     *
     * @param  Project  $project
     * @param  User  $user
     * @param  array{name: string}  $attributes
     * @return Label
     * Logic: enforce project ownership before writing a label and delegate persistence to the repository layer.
     */
    public function createLabel(Project $project, User $user, array $attributes): Label
    {
        abort_unless($project->owner_id === $user->id, 403);

        return $this->labelRepository->create($project, $attributes);
    }

    /**
     * Update a label within a project when the current user is the owner.
     *
     * @param  Project  $project
     * @param  Label  $label
     * @param  User  $user
     * @param  array{name: string}  $attributes
     * @return Label
     * Logic: confirm the label belongs to the target project and only then write the new name.
     */
    public function updateLabel(Project $project, Label $label, User $user, array $attributes): Label
    {
        abort_unless($project->owner_id === $user->id, 403);
        abort_unless($label->project_id === $project->id, 404);

        return $this->labelRepository->update($label, $attributes);
    }

    /**
     * Remove a label from a project when the current user owns it.
     *
     * @param  Project  $project
     * @param  Label  $label
     * @param  User  $user
     * @return void
     * Logic: enforce ownership and route scoping before deleting the label record.
     */
    public function deleteLabel(Project $project, Label $label, User $user): void
    {
        abort_unless($project->owner_id === $user->id, 403);
        abort_unless($label->project_id === $project->id, 404);

        $this->labelRepository->delete($label);
    }
}
