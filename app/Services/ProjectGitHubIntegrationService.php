<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectGitHubRepository;
use App\Repositories\ProjectGitHubRepositoryRepository;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProjectGitHubIntegrationService
{
    public function __construct(
        protected ProjectGitHubRepositoryRepository $projectGitHubRepositoryRepository,
    ) {}

    /**
     * Initialize a GitHub API client with the server-side token when configured.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     * Logic: centralize the GitHub authentication so both public and private repository access flow through one server-side credential path.
     */
    protected function githubHttp()
    {
        $token = trim((string) config('services.github.token'));
        $client = Http::accept('application/vnd.github+json')
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);

        if ($token !== '') {
            $client = $client->withToken($token);
        }

        return $client;
    }

    /**
     * Save or update the GitHub repository connection for a project.
     *
     * @param  Project  $project
     * @param  array{github_owner: string, github_repo: string, default_branch?: string|null, repository_url?: string|null, is_active?: bool|null}  $attributes
     * @return ProjectGitHubRepository
     * Logic: delegate the persistence to the repository layer so the integration contract remains isolated from the rest of the application.
     */
    public function connect(Project $project, array $attributes): ProjectGitHubRepository
    {
        return $this->projectGitHubRepositoryRepository->connect($project, $attributes);
    }

    /**
     * Return the configured GitHub repository for a project.
     *
     * @param  Project  $project
     * @return ProjectGitHubRepository|null
     * Logic: retrieve the saved repository connection so branch and PR operations are based on the project's intended target repository.
     */
    public function getForProject(Project $project): ?ProjectGitHubRepository
    {
        return $this->projectGitHubRepositoryRepository->findForProject($project);
    }

    /**
     * Inspect the configured GitHub repository and return a normalized metadata snapshot.
     *
     * @param  Project  $project
     * @return array{owner: string, repo: string, default_branch: string, repository_url: string, is_private: bool, is_archived: bool, is_valid: bool}
     * Logic: fetch the remote GitHub repository metadata for the project’s configured owner/repo and validate that it exists before any branch or pull-request work is attempted.
     */
    public function inspectRepository(Project $project): array
    {
        $connection = $this->projectGitHubRepositoryRepository->findForProject($project);

        if ($connection === null) {
            throw new RuntimeException('No GitHub repository is configured for this project.');
        }

        $response = $this->githubHttp()
            ->get(sprintf('%s/repos/%s/%s', rtrim((string) config('services.github.base_uri', 'https://api.github.com'), '/'), $connection->github_owner, $connection->github_repo));

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Could not inspect GitHub repository: %s/%s',
                $connection->github_owner,
                $connection->github_repo,
            ));
        }

        $payload = $response->json();

        return [
            'owner' => (string) ($payload['owner']['login'] ?? $connection->github_owner),
            'repo' => (string) ($payload['name'] ?? $connection->github_repo),
            'default_branch' => (string) ($payload['default_branch'] ?? $connection->default_branch ?? 'main'),
            'repository_url' => (string) ($payload['html_url'] ?? $connection->repository_url ?? sprintf('https://github.com/%s/%s', $connection->github_owner, $connection->github_repo)),
            'is_private' => (bool) ($payload['private'] ?? false),
            'is_archived' => (bool) ($payload['archived'] ?? false),
            'is_valid' => true,
        ];
    }

    /**
     * Create a new GitHub branch for the project from the configured default branch.
     *
     * @param  Project  $project
     * @param  string  $branchName
     * @param  string|null  $baseBranch
     * @return array{owner: string, repo: string, branch_name: string, base_branch: string, sha: string, created: bool}
     * Logic: resolve the base ref on the configured repository and create a new branch so later workflow steps can commit and push against an isolated branch.
     */
    public function createBranch(Project $project, string $branchName, ?string $baseBranch = null): array
    {
        $connection = $this->projectGitHubRepositoryRepository->findForProject($project);

        if ($connection === null) {
            throw new RuntimeException('No GitHub repository is configured for this project.');
        }

        $resolvedBaseBranch = trim((string) ($baseBranch ?? $connection->default_branch ?? 'main')) ?: 'main';
        $refResponse = $this->githubHttp()
            ->get(sprintf('%s/repos/%s/%s/git/ref/heads/%s', rtrim((string) config('services.github.base_uri', 'https://api.github.com'), '/'), $connection->github_owner, $connection->github_repo, $resolvedBaseBranch));

        if ($refResponse->failed()) {
            throw new RuntimeException(sprintf(
                'Could not create GitHub branch: %s/%s from %s',
                $connection->github_owner,
                $connection->github_repo,
                $resolvedBaseBranch,
            ));
        }

        $refPayload = $refResponse->json();
        $sha = (string) ($refPayload['object']['sha'] ?? '');

        if ($sha === '') {
            throw new RuntimeException(sprintf(
                'Could not create GitHub branch: %s/%s from %s',
                $connection->github_owner,
                $connection->github_repo,
                $resolvedBaseBranch,
            ));
        }

        $createResponse = $this->githubHttp()
            ->post(sprintf('%s/repos/%s/%s/git/refs', rtrim((string) config('services.github.base_uri', 'https://api.github.com'), '/'), $connection->github_owner, $connection->github_repo), [
                'ref' => 'refs/heads/'.$branchName,
                'sha' => $sha,
            ]);

        if ($createResponse->failed()) {
            throw new RuntimeException(sprintf(
                'Could not create GitHub branch: %s/%s from %s',
                $connection->github_owner,
                $connection->github_repo,
                $resolvedBaseBranch,
            ));
        }

        $payload = $createResponse->json();

        return [
            'owner' => $connection->github_owner,
            'repo' => $connection->github_repo,
            'branch_name' => (string) $branchName,
            'base_branch' => $resolvedBaseBranch,
            'sha' => (string) ($payload['object']['sha'] ?? $sha),
            'created' => true,
        ];
    }

    /**
     * Commit and push a set of file changes onto the configured branch.
     *
     * @param  Project  $project
     * @param  string  $branchName
     * @param  array<string, string>  $files
     * @param  string  $message
     * @return array{branch_name: string, commit_sha: string, pushed: bool}
     * Logic: write each file as a GitHub blob, assemble a new tree from the branch tip, create a commit, and update the branch ref so the workflow can continue to PR creation.
     */
    public function commitAndPush(Project $project, string $branchName, array $files, string $message): array
    {
        $connection = $this->projectGitHubRepositoryRepository->findForProject($project);

        if ($connection === null) {
            throw new RuntimeException('No GitHub repository is configured for this project.');
        }

        $refResponse = Http::accept('application/vnd.github+json')
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->get(sprintf('https://api.github.com/repos/%s/%s/git/ref/heads/%s', $connection->github_owner, $connection->github_repo, $branchName));

        if ($refResponse->failed()) {
            throw new RuntimeException(sprintf(
                'Could not commit and push GitHub changes: %s/%s on %s',
                $connection->github_owner,
                $connection->github_repo,
                $branchName,
            ));
        }

        $refPayload = $refResponse->json();
        $baseSha = (string) ($refPayload['object']['sha'] ?? '');

        if ($baseSha === '') {
            throw new RuntimeException(sprintf(
                'Could not commit and push GitHub changes: %s/%s on %s',
                $connection->github_owner,
                $connection->github_repo,
                $branchName,
            ));
        }

        $commitResponse = Http::accept('application/vnd.github+json')
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->get(sprintf('https://api.github.com/repos/%s/%s/git/commits/%s', $connection->github_owner, $connection->github_repo, $baseSha));

        if ($commitResponse->failed()) {
            throw new RuntimeException(sprintf(
                'Could not commit and push GitHub changes: %s/%s on %s',
                $connection->github_owner,
                $connection->github_repo,
                $branchName,
            ));
        }

        $baseCommit = $commitResponse->json();
        $baseTreeSha = (string) ($baseCommit['tree']['sha'] ?? '');

        if ($baseTreeSha === '') {
            throw new RuntimeException(sprintf(
                'Could not commit and push GitHub changes: %s/%s on %s',
                $connection->github_owner,
                $connection->github_repo,
                $branchName,
            ));
        }

        $treeEntries = [];
        foreach ($files as $path => $contents) {
            $blobResponse = Http::accept('application/vnd.github+json')
                ->withHeaders([
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->post(sprintf('https://api.github.com/repos/%s/%s/git/blobs', $connection->github_owner, $connection->github_repo), [
                    'content' => base64_encode($contents),
                    'encoding' => 'base64',
                ]);

            if ($blobResponse->failed()) {
                throw new RuntimeException(sprintf(
                    'Could not commit and push GitHub changes: %s/%s on %s',
                    $connection->github_owner,
                    $connection->github_repo,
                    $branchName,
                ));
            }

            $treeEntries[] = [
                'path' => (string) $path,
                'mode' => '100644',
                'type' => 'blob',
                'sha' => (string) $blobResponse->json('sha'),
            ];
        }

        $treeResponse = Http::accept('application/vnd.github+json')
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->post(sprintf('https://api.github.com/repos/%s/%s/git/trees', $connection->github_owner, $connection->github_repo), [
                'base_tree' => $baseTreeSha,
                'tree' => $treeEntries,
            ]);

        if ($treeResponse->failed()) {
            throw new RuntimeException(sprintf(
                'Could not commit and push GitHub changes: %s/%s on %s',
                $connection->github_owner,
                $connection->github_repo,
                $branchName,
            ));
        }

        $commitCreateResponse = Http::accept('application/vnd.github+json')
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->post(sprintf('https://api.github.com/repos/%s/%s/git/commits', $connection->github_owner, $connection->github_repo), [
                'message' => $message,
                'tree' => $treeResponse->json('sha'),
                'parents' => [$baseSha],
            ]);

        if ($commitCreateResponse->failed()) {
            throw new RuntimeException(sprintf(
                'Could not commit and push GitHub changes: %s/%s on %s',
                $connection->github_owner,
                $connection->github_repo,
                $branchName,
            ));
        }

        $newCommitSha = (string) $commitCreateResponse->json('sha');

        $updateRefResponse = Http::accept('application/vnd.github+json')
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->patch(sprintf('https://api.github.com/repos/%s/%s/git/refs/heads/%s', $connection->github_owner, $connection->github_repo, $branchName), [
                'sha' => $newCommitSha,
            ]);

        if ($updateRefResponse->failed()) {
            throw new RuntimeException(sprintf(
                'Could not commit and push GitHub changes: %s/%s on %s',
                $connection->github_owner,
                $connection->github_repo,
                $branchName,
            ));
        }

        return [
            'branch_name' => $branchName,
            'commit_sha' => $newCommitSha,
            'pushed' => true,
        ];
    }

    /**
     * Fetch the current GitHub pull request status and summarize the latest check runs for its head SHA.
     *
     * @param  Project  $project
     * @param  int  $pullRequestNumber
     * @return array{owner: string, repo: string, number: int, state: string, title: string, url: string, head_sha: string, base_branch: string, mergeable: bool|null, checks: array{total: int, success: int, failure: int, pending: int, skipped: int, overall: string}}
     * Logic: retrieve the remote pull request metadata and aggregate the latest GitHub check-run conclusions into a compact status summary that can drive workflow UI and approval decisions.
     */
    public function getPullRequestStatus(Project $project, int $pullRequestNumber): array
    {
        $connection = $this->projectGitHubRepositoryRepository->findForProject($project);

        if ($connection === null) {
            throw new RuntimeException('No GitHub repository is configured for this project.');
        }

        $pullRequestResponse = $this->githubHttp()
            ->get(sprintf('%s/repos/%s/%s/pulls/%d', rtrim((string) config('services.github.base_uri', 'https://api.github.com'), '/'), $connection->github_owner, $connection->github_repo, $pullRequestNumber));

        if ($pullRequestResponse->failed()) {
            throw new RuntimeException(sprintf(
                'Could not fetch GitHub pull request status: %s/%s #%d',
                $connection->github_owner,
                $connection->github_repo,
                $pullRequestNumber,
            ));
        }

        $pullRequest = $pullRequestResponse->json();
        $headSha = (string) ($pullRequest['head']['sha'] ?? '');
        $baseBranch = (string) ($pullRequest['base']['ref'] ?? $connection->default_branch ?? 'main');

        $checks = [
            'total' => 0,
            'success' => 0,
            'failure' => 0,
            'pending' => 0,
            'skipped' => 0,
            'overall' => 'unknown',
        ];

        if ($headSha !== '') {
            $checkResponse = $this->githubHttp()
                ->get(sprintf('%s/repos/%s/%s/commits/%s/check-runs', rtrim((string) config('services.github.base_uri', 'https://api.github.com'), '/'), $connection->github_owner, $connection->github_repo, $headSha));

            if ($checkResponse->failed()) {
                throw new RuntimeException(sprintf(
                    'Could not fetch GitHub pull request status: %s/%s #%d',
                    $connection->github_owner,
                    $connection->github_repo,
                    $pullRequestNumber,
                ));
            }

            $checkRuns = $checkResponse->json('check_runs', []);
            $checks['total'] = count($checkRuns);

            foreach ($checkRuns as $checkRun) {
                $conclusion = (string) ($checkRun['conclusion'] ?? '');
                $status = (string) ($checkRun['status'] ?? '');

                if ($conclusion === 'success') {
                    $checks['success']++;
                    continue;
                }

                if ($conclusion === 'failure') {
                    $checks['failure']++;
                    continue;
                }

                if ($conclusion === 'cancelled' || $conclusion === 'timed_out' || $conclusion === 'action_required' || $conclusion === 'neutral' || $conclusion === 'stale' || $conclusion === 'startup_failure') {
                    $checks['failure']++;
                    continue;
                }

                if ($conclusion === 'skipped') {
                    $checks['skipped']++;
                    continue;
                }

                if ($status === 'completed' || $status === 'queued' || $status === 'in_progress' || $status === 'pending' || $status === 'requested' || $status === 'waiting') {
                    $checks['pending']++;
                }
            }

            if ($checks['failure'] > 0) {
                $checks['overall'] = 'failure';
            } elseif ($checks['pending'] > 0) {
                $checks['overall'] = 'in_progress';
            } elseif ($checks['success'] > 0 || $checks['total'] === 0) {
                $checks['overall'] = 'success';
            } else {
                $checks['overall'] = 'unknown';
            }
        }

        return [
            'owner' => $connection->github_owner,
            'repo' => $connection->github_repo,
            'number' => (int) ($pullRequest['number'] ?? $pullRequestNumber),
            'state' => (string) ($pullRequest['state'] ?? 'open'),
            'title' => (string) ($pullRequest['title'] ?? ''),
            'url' => (string) ($pullRequest['html_url'] ?? ''),
            'head_sha' => $headSha,
            'base_branch' => $baseBranch,
            'mergeable' => array_key_exists('mergeable', $pullRequest) ? (bool) $pullRequest['mergeable'] : null,
            'checks' => $checks,
        ];
    }

    /**
     * Fetch the latest open GitHub pull request status for the project, if any exists.
     *
     * @param  Project  $project
     * @return array{owner: string, repo: string, number: int|null, state: string, title: string|null, url: string|null, head_sha: string|null, base_branch: string, mergeable: bool|null, checks: array{total: int, success: int, failure: int, pending: int, skipped: int, overall: string}}
     * Logic: resolve the project’s configured repository and select the newest open PR so the issue view can show the live PR state without requiring a separate, manual lookup.
     */
    public function getLatestOpenPullRequestStatus(Project $project): array
    {
        $connection = $this->projectGitHubRepositoryRepository->findForProject($project);

        if ($connection === null) {
            return [
                'owner' => '',
                'repo' => '',
                'number' => null,
                'state' => 'none',
                'title' => null,
                'url' => null,
                'head_sha' => null,
                'base_branch' => $connection?->default_branch ?? 'main',
                'mergeable' => null,
                'checks' => [
                    'total' => 0,
                    'success' => 0,
                    'failure' => 0,
                    'pending' => 0,
                    'skipped' => 0,
                    'overall' => 'no_pull_request',
                ],
            ];
        }

        $response = $this->githubHttp()
            ->get(sprintf('%s/repos/%s/%s/pulls?state=open&per_page=1', rtrim((string) config('services.github.base_uri', 'https://api.github.com'), '/'), $connection->github_owner, $connection->github_repo));

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Could not fetch GitHub pull request status: %s/%s',
                $connection->github_owner,
                $connection->github_repo,
            ));
        }

        $pullRequests = $response->json();

        if (! is_array($pullRequests) || count($pullRequests) === 0) {
            return [
                'owner' => $connection->github_owner,
                'repo' => $connection->github_repo,
                'number' => null,
                'state' => 'none',
                'title' => null,
                'url' => null,
                'head_sha' => null,
                'base_branch' => $connection->default_branch ?? 'main',
                'mergeable' => null,
                'checks' => [
                    'total' => 0,
                    'success' => 0,
                    'failure' => 0,
                    'pending' => 0,
                    'skipped' => 0,
                    'overall' => 'no_pull_request',
                ],
            ];
        }

        $pullRequest = $pullRequests[0];

        return $this->getPullRequestStatus($project, (int) ($pullRequest['number'] ?? 0));
    }

    /**
     * Create a GitHub pull request from the target branch into the default branch.
     *
     * @param  Project  $project
     * @param  string  $branchName
     * @param  string  $title
     * @param  string  $body
     * @param  string|null  $baseBranch
     * @return array{owner: string, repo: string, number: int, title: string, url: string, state: string}
     * Logic: open a pull request against the configured repository so the working branch can be reviewed before merge.
     */
    public function createPullRequest(Project $project, string $branchName, string $title, string $body, ?string $baseBranch = null): array
    {
        $connection = $this->projectGitHubRepositoryRepository->findForProject($project);

        if ($connection === null) {
            throw new RuntimeException('No GitHub repository is configured for this project.');
        }

        $resolvedBaseBranch = trim((string) ($baseBranch ?? $connection->default_branch ?? 'main')) ?: 'main';
        $response = $this->githubHttp()
            ->post(sprintf('%s/repos/%s/%s/pulls', rtrim((string) config('services.github.base_uri', 'https://api.github.com'), '/'), $connection->github_owner, $connection->github_repo), [
                'title' => $title,
                'head' => $branchName,
                'base' => $resolvedBaseBranch,
                'body' => $body,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Could not create GitHub pull request: %s/%s from %s to %s',
                $connection->github_owner,
                $connection->github_repo,
                $branchName,
                $resolvedBaseBranch,
            ));
        }

        $payload = $response->json();

        return [
            'owner' => $connection->github_owner,
            'repo' => $connection->github_repo,
            'number' => (int) ($payload['number'] ?? 0),
            'title' => (string) ($payload['title'] ?? $title),
            'url' => (string) ($payload['html_url'] ?? ''),
            'state' => (string) ($payload['state'] ?? 'open'),
        ];
    }
}
