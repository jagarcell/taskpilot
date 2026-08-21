<?php

namespace Database\Factories;

use App\Enums\AgentRunStatus;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentRun>
 */
class AgentRunFactory extends Factory
{
    protected $model = AgentRun::class;

    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'agent_id' => Agent::factory(),
            'user_id' => User::factory(),
            'model' => 'gpt-4o-mini',
            'provider' => 'openai',
            'status' => AgentRunStatus::PENDING,
            'input' => ['prompt' => $this->faker->sentence()],
            'output' => null,
            'error' => null,
            'started_at' => now(),
            'finished_at' => null,
            'token_usage' => null,
        ];
    }
}
