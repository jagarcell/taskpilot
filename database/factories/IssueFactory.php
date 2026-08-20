<?php

namespace Database\Factories;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'reporter_id' => User::factory(),
            'assignee_id' => User::factory(),
            'issue_key' => 'PRJ-'.$this->faker->unique()->numberBetween(1, 9999),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(IssueType::cases())->value,
            'status' => $this->faker->randomElement(IssueStatus::cases())->value,
            'priority' => $this->faker->randomElement(IssuePriority::cases())->value,
        ];
    }
}
