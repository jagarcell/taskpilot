<?php

namespace Database\Factories;

use App\Models\WorkflowDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowDefinition>
 */
class WorkflowDefinitionFactory extends Factory
{
    protected $model = WorkflowDefinition::class;

    public function definition(): array
    {
        return [
            'name' => 'Issue delivery workflow',
            'slug' => 'issue-delivery-workflow',
            'description' => 'Analyze, plan, approve, implement, test, and review a delivery task.',
            'steps' => ['analysis', 'planning', 'approval', 'implementation', 'testing', 'review'],
            'config' => [
                'requires_human_approval' => true,
                'default' => true,
            ],
            'is_enabled' => true,
        ];
    }
}
