<?php

namespace App\Contracts;

use App\Models\AgentRun;

interface AgentProvider
{
    /**
     * Execute the provider for a given agent run and return the structured output payload.
     *
     * @param  AgentRun  $agentRun
     * @return array<string, mixed>
     * Logic: isolate provider-specific execution so the issue domain stays agnostic to AI vendor details.
     */
    public function execute(AgentRun $agentRun): array;
}
