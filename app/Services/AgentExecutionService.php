<?php

namespace App\Services;

use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Repositories\AgentRunRepository;
use Throwable;

class AgentExecutionService
{
    public function __construct(
        protected AgentRunRepository $agentRunRepository,
        protected AgentProviderFactory $agentProviderFactory,
    ) {}

    /**
     * Execute an agent run through the configured provider and persist the result.
     *
     * @param  AgentRun  $agentRun
     * @return AgentRun
     * Logic: transition the run to running, invoke the provider abstraction, and record either completion or failure.
     */
    public function execute(AgentRun $agentRun): AgentRun
    {
        $this->agentRunRepository->updateStatus($agentRun, AgentRunStatus::RUNNING);

        try {
            $provider = $this->agentProviderFactory->resolve($agentRun->provider);
            $output = $provider->execute($agentRun);

            return $this->agentRunRepository->updateStatus($agentRun, AgentRunStatus::COMPLETED, [
                'output' => $output,
            ]);
        } catch (Throwable $exception) {
            return $this->agentRunRepository->updateStatus($agentRun, AgentRunStatus::FAILED, [
                'error' => [
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ],
            ]);
        }
    }
}
