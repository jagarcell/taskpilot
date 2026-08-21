<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssueActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueActivity>
 */
class IssueActivityFactory extends Factory
{
    protected $model = IssueActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'user_id' => User::factory(),
            'type' => 'issue_created',
            'message' => 'Issue created',
            'context' => [
                'title' => $this->faker->sentence(4),
            ],
        ];
    }
}
