<?php

namespace App\Repositories;

use App\Models\Label;
use App\Models\Project;

class LabelRepository
{
    /**
     * Create a label for a project.
     *
     * @param  Project  $project
     * @param  array{name: string}  $attributes
     * @return Label
     * Logic: persist the project-scoped label and return the created record for redirect or response use.
     */
    public function create(Project $project, array $attributes): Label
    {
        $label = $project->labels()->create([
            'name' => trim((string) $attributes['name']),
        ]);

        return $label->fresh();
    }

    /**
     * Update the label name.
     *
     * @param  Label  $label
     * @param  array{name: string}  $attributes
     * @return Label
     * Logic: rewrite the stored label name after ownership and project-scoping checks have already passed.
     */
    public function update(Label $label, array $attributes): Label
    {
        $label->update([
            'name' => trim((string) $attributes['name']),
        ]);

        return $label->fresh();
    }

    /**
     * Delete a label.
     *
     * @param  Label  $label
     * @return void
     * Logic: remove the label record after the service layer has confirmed the project owner and route ownership.
     */
    public function delete(Label $label): void
    {
        $label->delete();
    }
}
