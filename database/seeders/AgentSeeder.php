<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    /**
     * Seed the agents table with a default set of starter agents.
     */
    public function run(): void
    {
        $agents = [
            [
                'name' => 'Issue Analyzer',
                'slug' => 'issue-analyzer',
                'description' => 'Analyzes issue context, missing information, and likely causes.',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'is_active' => true,
            ],
            [
                'name' => 'Planning Agent',
                'slug' => 'planning-agent',
                'description' => 'Transforms issue analysis into an implementation plan and work breakdown.',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'is_active' => true,
            ],
            [
                'name' => 'Implementation Agent',
                'slug' => 'implementation-agent',
                'description' => 'Executes approved implementation work against the codebase.',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'is_active' => true,
            ],
            [
                'name' => 'Review Agent',
                'slug' => 'review-agent',
                'description' => 'Checks implementation quality, regressions, and review findings.',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'is_active' => true,
            ],
            [
                'name' => 'QA Agent',
                'slug' => 'qa-agent',
                'description' => 'Validates workflows, edge cases, and acceptance criteria coverage.',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'is_active' => true,
            ],
        ];

        foreach ($agents as $agent) {
            Agent::updateOrCreate(
                ['slug' => $agent['slug']],
                $agent,
            );
        }
    }
}
