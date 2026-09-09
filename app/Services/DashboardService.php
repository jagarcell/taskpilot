<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\User;
use App\Repositories\AgentRepository;
use App\Repositories\IssueRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ProviderOAuthCredentialRepository;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    public function __construct(
        protected AgentRepository $agentRepository,
        protected ProjectRepository $projectRepository,
        protected IssueRepository $issueRepository,
        protected ProviderOAuthCredentialRepository $providerOAuthCredentialRepository,
    ) {}

    /**
     * Fetch the data required to render the dashboard.
     *
     * @param  User  $user
     * @return array<string, mixed>
     * Logic: assemble the dashboard payload from the repository layer so the controller stays focused on response composition.
     */
    public function getDashboardPayload(User $user): array
    {
        return [
            'agents' => $this->agentRepository->listForDashboard()->map(fn (Agent $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'slug' => $agent->slug,
                'description' => $agent->description,
                'provider' => $agent->provider,
                'model' => $agent->model,
                'is_active' => (bool) $agent->is_active,
            ])->all(),
            'active_projects_count' => $this->projectRepository->countForUser($user),
            'open_issues_count' => $this->issueRepository->countOpenIssuesForUser($user),
            'open_issues' => $this->issueRepository->listOpenIssuesForUser($user)->map(fn ($issue) => [
                'id' => $issue->id,
                'project_id' => $issue->project_id,
                'issue_key' => $issue->issue_key,
                'title' => $issue->title,
                'description' => $issue->description,
                'type' => $issue->type->value ?? $issue->type,
                'status' => $issue->status->value ?? $issue->status,
                'priority' => $issue->priority->value ?? $issue->priority,
                'project_name' => $issue->project?->name,
                'reporter_name' => $issue->reporter?->name,
                'assignee_name' => $issue->assignee?->name,
                'detail_url' => route('projects.issues.show', ['project' => $issue->project_id, 'issue' => $issue->id]),
            ])->all(),
            'ready_for_review_count' => $this->issueRepository->countReadyForReviewIssuesForUser($user),
            'ready_for_review_issues' => $this->issueRepository->listReadyForReviewIssuesForUser($user)->map(fn ($issue) => [
                'id' => $issue->id,
                'project_id' => $issue->project_id,
                'issue_key' => $issue->issue_key,
                'title' => $issue->title,
                'description' => $issue->description,
                'type' => $issue->type->value ?? $issue->type,
                'status' => $issue->status->value ?? $issue->status,
                'priority' => $issue->priority->value ?? $issue->priority,
                'project_name' => $issue->project?->name,
                'reporter_name' => $issue->reporter?->name,
                'assignee_name' => $issue->assignee?->name,
                'detail_url' => route('projects.issues.show', ['project' => $issue->project_id, 'issue' => $issue->id]),
            ])->all(),
            'provider_oauth_credentials' => $this->providerOAuthCredentialRepository->getForUser($user)
                ->mapWithKeys(fn ($credential) => [
                    $credential->provider => [
                        'provider' => $credential->provider,
                        'client_id' => $credential->client_id,
                        'client_secret' => $this->maskClientSecret((string) $credential->client_secret),
                        'redirect_uri' => $credential->redirect_uri,
                    ],
                ])
                ->all(),
        ];
    }

    /**
     * Mask a client secret for display in the dashboard.
     *
     * @param  string  $value
     * @return string
     * Logic: preserve the ending characters for recognition while hiding the secret from the browser view.
     */
    protected function maskClientSecret(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 8) {
            return str_repeat('*', max(0, strlen($value)));
        }

        return str_repeat('*', strlen($value) - 8).substr($value, -8);
    }
}
