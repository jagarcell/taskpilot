<?php

namespace App\Http\Requests;

use App\Services\AgentProviderFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * Logic: only authenticated users may store agent definitions for the shared catalog.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string|bool>>
     * Logic: require all agent-definition fields needed to manage the model provider and activation state.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'provider' => ['required', 'string', 'max:255', Rule::in(AgentProviderFactory::supportedProviders())],
            'model' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
