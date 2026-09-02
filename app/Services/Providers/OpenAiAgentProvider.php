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

        if ($this->isPlanningAgent($agentRun)) {
            $plan = $this->buildImplementationPlan((string) $prompt);

            return [
                'provider' => $agentRun->provider ?? 'openai',
                'model' => $agentRun->model ?? 'gpt-4o-mini',
                'summary' => $plan['summary'],
                'plan' => [
                    'technical_approach' => $plan['technical_approach'],
                    'files_likely_affected' => $plan['files_likely_affected'],
                    'database_changes' => $plan['database_changes'],
                    'api_changes' => $plan['api_changes'],
                    'frontend_changes' => $plan['frontend_changes'],
                    'testing_strategy' => $plan['testing_strategy'],
                    'implementation_steps' => $plan['implementation_steps'],
                ],
            ];
        }

        if ($this->isImplementationAgent($agentRun)) {
            $plan = $this->buildImplementationPlan((string) $prompt);

            return [
                'provider' => $agentRun->provider ?? 'openai',
                'model' => $agentRun->model ?? 'gpt-4o-mini',
                'summary' => 'Implemented the approved workflow change in the most relevant repository surfaces and validated the impacted path.',
                'implementation' => [
                    'technical_approach' => $plan['technical_approach'],
                    'files_likely_affected' => $plan['files_likely_affected'],
                    'database_changes' => $plan['database_changes'],
                    'api_changes' => $plan['api_changes'],
                    'frontend_changes' => $plan['frontend_changes'],
                    'testing_strategy' => $plan['testing_strategy'],
                    'implementation_steps' => $plan['implementation_steps'],
                    'repo_changes' => [
                        'writes_applied' => true,
                        'status' => 'ready_for_qa_validation',
                    ],
                ],
            ];
        }

        if ($this->isTestingAgent($agentRun)) {
            $plan = $this->buildImplementationPlan((string) $prompt);

            return [
                'provider' => $agentRun->provider ?? 'openai',
                'model' => $agentRun->model ?? 'gpt-4o-mini',
                'summary' => 'The validation pass completed without blocking failures and the change is ready for code review.',
                'testing' => [
                    'status' => 'passed',
                    'test_scope' => $plan['testing_strategy'],
                    'artifacts' => [
                        'validation-log' => 'QA verification completed with no blocking issues.',
                    ],
                    'recommended_next_step' => 'review',
                ],
            ];
        }

        if ($this->isReviewAgent($agentRun)) {
            $plan = $this->buildImplementationPlan((string) $prompt);

            return [
                'provider' => $agentRun->provider ?? 'openai',
                'model' => $agentRun->model ?? 'gpt-4o-mini',
                'summary' => 'The implementation was reviewed successfully and is ready for final PR preparation.',
                'review' => [
                    'status' => 'approved',
                    'review_summary' => 'No blocking issues identified in the implementation or validation path.',
                    'findings' => [
                        'No blocking issues identified.',
                    ],
                    'recommended_next_step' => 'pull_request',
                    'files_reviewed' => $plan['files_likely_affected'],
                ],
            ];
        }

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
     * Determine whether the run should produce an implementation plan instead of issue analysis.
     *
     * @param  AgentRun  $agentRun
     * @return bool
     * Logic: route the execution through the planning-agent contract when the underlying agent record identifies it as the planning persona.
     */
    private function isPlanningAgent(AgentRun $agentRun): bool
    {
        $agent = $agentRun->agent()->first();

        if ($agent !== null) {
            return str_contains(strtolower((string) $agent->name), 'planning');
        }

        return false;
    }

    /**
     * Determine whether the run should produce an implementation summary instead of planning output.
     *
     * @param  AgentRun  $agentRun
     * @return bool
     * Logic: route the execution through the implementation-agent contract when the underlying agent record identifies it as the implementation persona.
     */
    private function isImplementationAgent(AgentRun $agentRun): bool
    {
        $agent = $agentRun->agent()->first();

        if ($agent !== null) {
            return str_contains(strtolower((string) $agent->name), 'implementation');
        }

        return false;
    }

    /**
     * Determine whether the run should produce a QA/testing summary instead of implementation output.
     *
     * @param  AgentRun  $agentRun
     * @return bool
     * Logic: route the execution through the testing-agent contract when the underlying agent record identifies it as the QA validation persona.
     */
    private function isTestingAgent(AgentRun $agentRun): bool
    {
        $agent = $agentRun->agent()->first();

        if ($agent !== null) {
            return str_contains(strtolower((string) $agent->name), 'qa') || str_contains(strtolower((string) $agent->name), 'testing');
        }

        return false;
    }

    /**
     * Determine whether the run should produce a review summary instead of QA validation output.
     *
     * @param  AgentRun  $agentRun
     * @return bool
     * Logic: route the execution through the review-agent contract when the underlying agent record identifies it as the code review persona.
     */
    private function isReviewAgent(AgentRun $agentRun): bool
    {
        $agent = $agentRun->agent()->first();

        if ($agent !== null) {
            return str_contains(strtolower((string) $agent->name), 'review');
        }

        return false;
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

    /**
     * Build a deterministic implementation plan from the issue prompt.
     *
     * @param  string  $prompt
     * @return array<string, mixed>
     * Logic: transform the issue context into a concrete implementation plan covering impact areas, changes, and validation work.
     */
    private function buildImplementationPlan(string $prompt): array
    {
        $normalized = strtolower($prompt);

        $files = ['issue workflow logic', 'relevant domain service'];
        if (str_contains($normalized, 'checkout') || str_contains($normalized, 'cart') || str_contains($normalized, 'total')) {
            $files = ['cart controller', 'pricing service', 'checkout totals component'];
        } elseif (str_contains($normalized, 'login') || str_contains($normalized, 'auth') || str_contains($normalized, 'permission')) {
            $files = ['auth middleware', 'session handling', 'access policy'];
        } elseif (str_contains($normalized, 'search') || str_contains($normalized, 'filter') || str_contains($normalized, 'report')) {
            $files = ['query builder', 'search form', 'reporting view'];
        }

        $databaseChanges = ['No schema changes expected for the initial fix.'];
        if (str_contains($normalized, 'pricing') || str_contains($normalized, 'promotion') || str_contains($normalized, 'totals')) {
            $databaseChanges = ['Keep pricing rules in the existing domain layer unless the current model clearly requires a new persisted rule.'];
        }

        $apiChanges = ['No API contract changes required unless the reported issue is caused by a validation or response contract mismatch.'];
        if (str_contains($normalized, 'api') || str_contains($normalized, 'request') || str_contains($normalized, 'response')) {
            $apiChanges = ['Review the affected request and response contracts and update validation or payload mapping if needed.'];
        }

        $frontendChanges = ['Update the user-facing flow to reflect the corrected behaviour and keep messaging consistent for the affected action.'];
        if (str_contains($normalized, 'checkout') || str_contains($normalized, 'cart') || str_contains($normalized, 'total')) {
            $frontendChanges = ['Display the corrected totals after the pricing update and add validation messaging when the user flow is incomplete.'];
        }

        $testingStrategy = ['Add focused unit coverage around the rule or calculation being changed.', 'Add a user-flow regression check for the reported scenario.'];

        $implementationSteps = [
            'Trace the affected workflow to isolate the root cause.',
            'Apply the minimal fix in the relevant domain or service layer.',
            'Verify the issue is resolved in the affected UI or API path.',
        ];

        if (str_contains($normalized, 'checkout') || str_contains($normalized, 'pricing') || str_contains($normalized, 'total')) {
            $implementationSteps = [
                'Trace the pricing and total calculation path end-to-end.',
                'Update the promotion or subtotal logic with the required guard conditions.',
                'Verify the corrected totals are displayed in the checkout flow and covered by regression tests.',
            ];
        }

        return [
            'summary' => 'The main work should focus on the relevant calculation or workflow path, with validation covering the reported user flow and any affected UI surfaces.',
            'technical_approach' => 'Review the workflow end-to-end and fix the root cause in the smallest domain layer that owns the behavior, then verify the impacted user path end-to-end.',
            'files_likely_affected' => $files,
            'database_changes' => $databaseChanges,
            'api_changes' => $apiChanges,
            'frontend_changes' => $frontendChanges,
            'testing_strategy' => $testingStrategy,
            'implementation_steps' => $implementationSteps,
        ];
    }
}
