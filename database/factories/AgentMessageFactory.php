<?php

namespace Database\Factories;

use App\Models\AgentMessage;
use App\Models\AgentRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentMessage>
 */
class AgentMessageFactory extends Factory
{
    protected $model = AgentMessage::class;

    public function definition(): array
    {
        return [
            'agent_run_id' => AgentRun::factory(),
            'role' => 'assistant',
            'content' => $this->faker->sentence(),
            'metadata' => ['source' => 'analysis'],
        ];
    }
}
