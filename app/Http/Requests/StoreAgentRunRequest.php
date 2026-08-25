<?php

namespace App\Http\Requests;

use App\Models\Agent;
use App\Services\AgentProviderFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * Logic: only authenticated project members may request an agent run for an issue in that project.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     * Logic: validate the agent run payload so the run can be created against a real agent and a valid issue context.
     */
    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', Rule::exists(Agent::class, 'id')],
            'model' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255', Rule::in(AgentProviderFactory::supportedProviders())],
            'input' => ['nullable', 'array'],
            'input.*' => ['nullable'],
        ];
    }
}
