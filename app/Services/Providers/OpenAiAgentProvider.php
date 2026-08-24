<?php

namespace App\Services\Providers;

use App\Contracts\AgentProvider;
use App\Models\AgentRun;

class OpenAiAgentProvider implements AgentProvider
{
    /**
     * Execute an agent run using the configured provider and return a structured payload.
     *
     * @param  AgentRun  $agentRun
     * @return array<string, mixed>
     * Logic: provide a safe default execution payload for the provider abstraction until a real vendor SDK is wired in.
     */
    public function execute(AgentRun $agentRun): array
    {
        $prompt = is_array($agentRun->input) ? ($agentRun->input['prompt'] ?? 'No prompt provided.') : 'No prompt provided.';

        return [
            'provider' => $agentRun->provider ?? 'openai',
            'model' => $agentRun->model ?? 'gpt-4o-mini',
            'summary' => 'Agent execution completed successfully.',
            'analysis' => [
                'prompt' => $prompt,
                'issue_id' => $agentRun->issue_id,
                'agent_id' => $agentRun->agent_id,
            ],
        ];
    }
}
