<?php

namespace App\Repositories;

use App\Models\WorkflowDefinition;

class WorkflowDefinitionRepository
{
    /**
     * Fetch the enabled workflow definition marked as the default for new issue runs.
     *
     * @return WorkflowDefinition|null
     * Logic: centralize the database lookup for the default workflow so the service layer can query through the repository boundary.
     */
    public function findDefaultEnabled(): ?WorkflowDefinition
    {
        return WorkflowDefinition::query()
            ->where('is_enabled', true)
            ->where('config->default', true)
            ->first();
    }

    /**
     * Create the default fallback workflow definition used when no valid definition exists.
     *
     * @return WorkflowDefinition
     * Logic: provide a known-safe workflow definition with the core analysis/planning/approval sequence for issue orchestration.
     */
    public function createDefault(): WorkflowDefinition
    {
        return WorkflowDefinition::query()->create([
            'name' => 'Default issue workflow',
            'slug' => 'default-issue-workflow',
            'description' => 'Default workflow for issue orchestration.',
            'steps' => ['analysis', 'planning', 'approval', 'implementation', 'testing', 'review'],
            'config' => ['default' => true, 'requires_human_approval' => true],
            'is_enabled' => true,
        ]);
    }
}
