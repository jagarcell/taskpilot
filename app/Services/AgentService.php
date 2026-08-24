<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\User;
use App\Repositories\AgentRepository;

class AgentService
{
    public function __construct(
        protected AgentRepository $agentRepository,
    ) {}

    /**
     * Create a new agent definition for the authenticated user.
     *
     * @param  User  $user
     * @param  array{name: string, description?: string|null, provider?: string, model?: string, is_active?: bool}  $attributes
     * @return Agent
     * Logic: keep the current user as context for the creation flow and delegate persistence to the repository layer.
     */
    public function createAgent(User $user, array $attributes): Agent
    {
        abort_unless($user instanceof User, 403);

        return $this->agentRepository->create($attributes);
    }

    /**
     * Update an agent definition and its activation status.
     *
     * @param  User  $user
     * @param  Agent  $agent
     * @param  array{name?: string, description?: string|null, provider?: string, model?: string, is_active?: bool}  $attributes
     * @return Agent
     * Logic: preserve the authenticated user context while delegating the actual update to the repository.
     */
    public function updateAgent(User $user, Agent $agent, array $attributes): Agent
    {
        abort_unless($user instanceof User, 403);

        return $this->agentRepository->update($agent, $attributes);
    }
}
