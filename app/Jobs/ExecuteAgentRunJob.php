<?php

namespace App\Jobs;

use App\Models\AgentRun;
use App\Services\AgentExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteAgentRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  AgentRun  $agentRun
     */
    public function __construct(
        public AgentRun $agentRun,
    ) {}

    /**
     * Execute the queued agent run.
     *
     * @param  AgentExecutionService  $agentExecutionService
     * @return void
     * Logic: move provider execution onto the queue and persist lifecycle updates as the run progresses.
     */
    public function handle(AgentExecutionService $agentExecutionService): void
    {
        $agentExecutionService->execute($this->agentRun);
    }

    /**
     * Persist the failure state when the queued job crashes or is rejected by the queue worker.
     *
     * @param  Throwable  $exception
     * @return void
     * Logic: ensure a broken queued agent execution still marks the agent and workflow runs as failed so the retry button is exposed.
     */
    public function failed(Throwable $exception): void
    {
        $agentRun = $this->agentRun->fresh();

        if ($agentRun === null) {
            return;
        }

        app(AgentExecutionService::class)->handleJobFailure($agentRun, $exception);
    }
}
