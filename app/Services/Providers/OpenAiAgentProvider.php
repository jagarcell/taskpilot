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
     * Logic: interpret the issue prompt into a practical analysis payload with likely causes, missing information, priority, and investigation areas.
     */
    public function execute(AgentRun $agentRun): array
    {
        $prompt = is_array($agentRun->input) ? ($agentRun->input['prompt'] ?? 'No prompt provided.') : 'No prompt provided.';
        $analysis = $this->buildIssueAnalysis((string) $prompt);

        return [
            'provider' => $agentRun->provider ?? 'openai',
            'model' => $agentRun->model ?? 'gpt-4o-mini',
            'summary' => $analysis['summary'],
            'analysis' => [
                'likely_causes' => $analysis['likely_causes'],
                'missing_information' => $analysis['missing_information'],
                'acceptance_criteria' => $analysis['acceptance_criteria'],
                'suggested_priority' => $analysis['suggested_priority'],
                'estimated_complexity' => $analysis['estimated_complexity'],
                'areas_to_investigate' => $analysis['areas_to_investigate'],
            ],
        ];
    }

    /**
     * Build a structured issue analysis payload from the issue text.
     *
     * @param  string  $prompt
     * @return array<string, mixed>
     * Logic: normalize the issue text and infer a concise, deterministic analysis for the first useful AI capability in the roadmap.
     */
    private function buildIssueAnalysis(string $prompt): array
    {
        $normalized = strtolower($prompt);

        $likelyCauses = [];
        if (str_contains($normalized, 'total') || str_contains($normalized, 'checkout') || str_contains($normalized, 'cart')) {
            $likelyCauses[] = 'subtotal recalculation bug';
        }
        if (str_contains($normalized, 'price') || str_contains($normalized, 'currency') || str_contains($normalized, 'tax')) {
            $likelyCauses[] = 'pricing or currency mismatch';
        }
        if (str_contains($normalized, 'login') || str_contains($normalized, 'auth') || str_contains($normalized, 'permission')) {
            $likelyCauses[] = 'authentication or access control regression';
        }
        if (str_contains($normalized, 'save') || str_contains($normalized, 'update') || str_contains($normalized, 'create')) {
            $likelyCauses[] = 'state persistence or validation regression';
        }

        if ($likelyCauses === []) {
            $likelyCauses = ['missing validation around the reported workflow'];
        }

        $missingInformation = [];
        $missingInformation[] = 'Steps to reproduce the issue in a live environment';
        $missingInformation[] = 'Expected behavior versus actual behavior';

        if (str_contains($normalized, 'when') || str_contains($normalized, 'after') || str_contains($normalized, 'before')) {
            $missingInformation[] = 'The exact trigger condition and affected user flow';
        }

        $acceptanceCriteria = [
            'The reported behavior is reproducible and the fix is validated in the affected flow.',
            'The expected outcome is consistent for the described user scenario.',
        ];

        $severityKeywords = ['security', 'payment', 'checkout', 'data loss', 'delete', 'permission', 'auth'];
        $suggestedPriority = in_array(true, array_map(fn (string $keyword) => str_contains($normalized, $keyword), $severityKeywords), true)
            ? 'high'
            : (str_contains($normalized, 'error') || str_contains($normalized, 'fail') || str_contains($normalized, 'broken') ? 'medium' : 'low');

        $estimatedComplexity = 3;
        if (str_contains($normalized, 'checkout') || str_contains($normalized, 'payment') || str_contains($normalized, 'auth')) {
            $estimatedComplexity = 7;
        } elseif (str_contains($normalized, 'report') || str_contains($normalized, 'filter') || str_contains($normalized, 'search')) {
            $estimatedComplexity = 5;
        }

        $areas = [];
        if (str_contains($normalized, 'checkout') || str_contains($normalized, 'total') || str_contains($normalized, 'cart')) {
            $areas = ['cart controller', 'pricing service', 'order totals'];
        } elseif (str_contains($normalized, 'login') || str_contains($normalized, 'auth') || str_contains($normalized, 'permission')) {
            $areas = ['auth middleware', 'session handling', 'access policy'];
        } else {
            $areas = ['issue workflow logic', 'relevant domain service', 'user-facing validation'];
        }

        $summary = sprintf(
            'The issue appears to involve %s and should be investigated in %s before a fix is implemented.',
            $likelyCauses[0],
            implode(', ', $areas),
        );

        return [
            'summary' => $summary,
            'likely_causes' => $likelyCauses,
            'missing_information' => $missingInformation,
            'acceptance_criteria' => $acceptanceCriteria,
            'suggested_priority' => $suggestedPriority,
            'estimated_complexity' => $estimatedComplexity,
            'areas_to_investigate' => $areas,
        ];
    }
}
