<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use App\Services\AgentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AgentController extends Controller
{
    public function __construct(
        protected AgentService $agentService,
    ) {}

    /**
     * Create a new agent definition.
     *
     * @param  StoreAgentRequest  $request
     * @return RedirectResponse
     * Logic: validate the agent payload and persist a new definition using the authenticated user context.
     */
    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->agentService->createAgent(Auth::user(), $validated);

        return redirect()->route('dashboard');
    }

    /**
     * Update an existing agent definition and activation state.
     *
     * @param  UpdateAgentRequest  $request
     * @param  Agent  $agent
     * @return RedirectResponse
     * Logic: confirm the user is authenticated, then update the agent definition or toggle its active state.
     */
    public function update(UpdateAgentRequest $request, Agent $agent): RedirectResponse
    {
        $validated = $request->validated();

        $this->agentService->updateAgent(Auth::user(), $agent, $validated);

        return redirect()->route('dashboard');
    }
}
