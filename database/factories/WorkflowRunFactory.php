<?php

namespace Database\Factories;

use App\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowRun>
 */
class WorkflowRunFactory extends Factory
{
    protected $model = WorkflowRun::class;

    public function definition(): array
    {
        return [
            'current_step' => 'analysis',
            'status' => 'pending',
            'metadata' => [
                'started_from' => 'issue_detail_page',
            ],
        ];
    }
}
