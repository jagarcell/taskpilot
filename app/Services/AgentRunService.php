<?php

namespace App\Services;

use App\Enums\AgentRunStatus;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectRepositoryBinding;
use App\Models\User;
use App\Repositories\AgentRunRepository;
use App\Repositories\IssueRepository;

class AgentRunService
{
    protected IssueRepository $issueRepository;

    public function __construct(
        protected AgentRunRepository $agentRunRepository,
        ?IssueRepository $issueRepository = null,
    ) {
        $this->issueRepository = $issueRepository ?? app(IssueRepository::class);
    }

    /**
     * Create a new pending agent run against an issue.
     *
     * @param  Agent  $agent
     * @param  Issue  $issue
     * @param  User  $user
     * @param  array<string, mixed>  $attributes
     * @return AgentRun
     * Logic: validate the issue context and persist a new run in the pending state so the workflow can observe it.
     */
    public function createRun(Agent $agent, Issue $issue, User $user, array $attributes): AgentRun
    {
        $attributes = $this->enrichRepositoryContext($agent, $issue, $attributes);
        $attributes = $this->enrichPlanningPrompt($agent, $issue, $attributes);
        $run = $this->agentRunRepository->create($agent, $issue, $user, $attributes);

        ExecuteAgentRunJob::dispatch($run);

        return $run;
    }

    /**
     * Enrich execution prompts with the active project repository binding when one exists.
     *
     * @param  Agent  $agent
     * @param  Issue  $issue
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     * Logic: append the bound repository metadata to the prompt so analysis and planning run against the actual selected repository rather than a generic issue narrative.
     */
    protected function enrichRepositoryContext(Agent $agent, Issue $issue, array $attributes): array
    {
        $project = $issue->project()->first();

        if ($project === null) {
            return $attributes;
        }

        $binding = $project->repositoryBinding()->first();

        if (! $binding instanceof ProjectRepositoryBinding) {
            return $attributes;
        }

        $prompt = (string) ($attributes['input']['prompt'] ?? '');
        $issueDetails = [
            'Issue title: '.$issue->title,
            'Issue description: '.($issue->description ?: 'No description provided.'),
        ];

        if ($prompt !== '') {
            $issueDetails[] = 'Original prompt: '.$prompt;
        }

        $context = [
            'Repository context:',
            'Project: '.($project->name ?: 'Unnamed project'),
            'Provider: '.($binding->provider ?? 'github'),
            'Binding type: '.($binding->binding_type ?? 'remote'),
        ];

        if (($binding->binding_type ?? 'remote') === 'remote') {
            $owner = trim((string) ($binding->remote_owner ?? ''));
            $repo = trim((string) ($binding->remote_repo ?? ''));
            $context[] = 'Remote repository: '.($owner !== '' && $repo !== '' ? $owner.'/'.$repo : 'not configured');
            $remoteUrl = trim((string) ($binding->remote_url ?? ''));
            if ($remoteUrl !== '') {
                $context[] = 'Remote URL: '.$remoteUrl;
            }
            $defaultBranch = trim((string) ($binding->default_branch ?? ''));
            if ($defaultBranch !== '') {
                $context[] = 'Default branch: '.$defaultBranch;
            }
        } else {
            $localPath = trim((string) ($binding->local_path ?? ''));
            $context[] = 'Local path: '.($localPath !== '' ? $localPath : 'not configured');
        }

        $status = $binding->verified_at !== null ? 'verified' : 'pending';
        $context[] = 'Repository status: '.$status;

        $attributes['input']['prompt'] = implode("\n\n", [...$issueDetails, implode("\n", $context)]);

        return $attributes;
    }

    /**
     * Enrich planning-agent requests with the latest issue analysis context when available.
     *
     * @param  Agent  $agent
     * @param  Issue  $issue
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     * Logic: augment planning prompts with the issue title, description, and latest analyzer output so the planner can generate grounded implementation steps.
     */
    protected function enrichPlanningPrompt(Agent $agent, Issue $issue, array $attributes): array
    {
        if (! str_contains(strtolower((string) $agent->name), 'planning')) {
            return $attributes;
        }

        $prompt = $attributes['input']['prompt'] ?? '';
        $latestAnalysis = $this->agentRunRepository->findLatestAnalysisForIssue($issue);
        $analysisContext = [];

        if ($latestAnalysis !== null && is_array($latestAnalysis->output)) {
            if (isset($latestAnalysis->output['summary']) && is_string($latestAnalysis->output['summary']) && $latestAnalysis->output['summary'] !== '') {
                $analysisContext[] = 'Latest analysis summary: '.$latestAnalysis->output['summary'];
            }

            if (isset($latestAnalysis->output['analysis']) && is_array($latestAnalysis->output['analysis'])) {
                foreach ($latestAnalysis->output['analysis'] as $key => $value) {
                    if (is_array($value)) {
                        $analysisContext[] = ucfirst(str_replace('_', ' ', (string) $key)).': '.implode(', ', array_filter(array_map('strval', $value)));
                    } elseif (is_string($value) && $value !== '') {
                        $analysisContext[] = ucfirst(str_replace('_', ' ', (string) $key)).': '.$value;
                    }
                }
            }
        }

        if ($analysisContext === []) {
            return $attributes;
        }

        $attributes['input']['prompt'] = $prompt === ''
            ? implode("\n\n", [
                'Issue title: '.$issue->title,
                'Issue description: '.($issue->description ?: 'No description provided.'),
                'Latest analysis context:',
                implode("\n", $analysisContext),
            ])
            : $prompt."\n\nLatest analysis context:\n".implode("\n", $analysisContext);

        return $attributes;
    }

    /**
     * Create a run for an issue that belongs to the current project and user access context.
     *
     * @param  Project  $project
     * @param  Issue  $issue
     * @param  User  $user
     * @param  Agent  $agent
     * @param  array<string, mixed>  $attributes
     * @return AgentRun
     * Logic: enforce project membership and issue ownership before creating a queued or pending execution record.
     */
    public function createRunForIssue(Project $project, Issue $issue, User $user, Agent $agent, array $attributes): AgentRun
    {
        abort_unless($issue->project_id === $project->id, 404);
        abort_unless($this->issueRepository->userHasAccessToProject($project, $user), 403);
        abort_unless($agent->is_active, 422);

        return $this->createRun($agent, $issue, $user, $attributes);
    }

    /**
     * Mark an agent run as completed and keep the output payload.
     *
     * @param  AgentRun  $agentRun
     * @param  array<string, mixed>  $output
     * @return AgentRun
     * Logic: transition the run into the completed state while preserving whatever result the agent produced.
     */
    public function markRunAsCompleted(AgentRun $agentRun, array $output): AgentRun
    {
        return $this->agentRunRepository->updateStatus($agentRun, AgentRunStatus::COMPLETED, [
            'output' => $output,
        ]);
    }
}
