<?php

namespace App\Repositories;

use App\Models\Agent;
use Illuminate\Support\Str;

class AgentRepository
{
    /**
     * Create an agent definition.
     *
     * @param  array{name: string, description?: string|null, provider?: string, model?: string, is_active?: bool}  $attributes
     * @return Agent
     * Logic: persist a new agent definition with a unique slug derived from its name.
     */
    public function create(array $attributes): Agent
    {
        $agent = Agent::query()->create([
            'name' => trim((string) $attributes['name']),
            'slug' => $this->uniqueSlug((string) $attributes['name']),
            'description' => $attributes['description'] ?? null,
            'provider' => trim((string) ($attributes['provider'] ?? 'openai')),
            'model' => trim((string) ($attributes['model'] ?? 'gpt-4o-mini')),
            'is_active' => (bool) ($attributes['is_active'] ?? false),
        ]);

        return $agent->fresh();
    }

    /**
     * Update the agent definition and its active flag.
     *
     * @param  Agent  $agent
     * @param  array{name?: string, description?: string|null, provider?: string, model?: string, is_active?: bool}  $attributes
     * @return Agent
     * Logic: rewrite the mutable fields while preserving uniqueness on the agent slug.
     */
    public function update(Agent $agent, array $attributes): Agent
    {
        $payload = [
            'name' => trim((string) ($attributes['name'] ?? $agent->name)),
            'description' => $attributes['description'] ?? $agent->description,
            'provider' => trim((string) ($attributes['provider'] ?? $agent->provider)),
            'model' => trim((string) ($attributes['model'] ?? $agent->model)),
            'is_active' => isset($attributes['is_active']) ? (bool) $attributes['is_active'] : $agent->is_active,
        ];

        if (($payload['name'] ?? '') !== $agent->name) {
            $payload['slug'] = $this->uniqueSlug((string) $payload['name'], $agent->id);
        }

        $agent->update($payload);

        return $agent->fresh();
    }

    /**
     * Generate a unique slug for the agent name.
     *
     * @param  string  $name
     * @param  int|null  $ignoreId
     * @return string
     * Logic: derive a slug from the name and ensure it does not collide with another active definition.
     */
    public function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim($name)) ?: 'agent';
        $slug = $base;
        $counter = 2;

        while (Agent::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = sprintf('%s-%d', $base, $counter);
            $counter++;
        }

        return $slug;
    }

    /**
     * Retrieve the active agent matching the supplied name.
     *
     * @param  string  $name
     * @return Agent|null
     * Logic: centralize the active-agent lookup so workflow orchestration can resolve step agents without direct DB access in the service layer.
     */
    public function findActiveByName(string $name): ?Agent
    {
        return Agent::query()
            ->where('name', $name)
            ->where('is_active', true)
            ->first();
    }
}
