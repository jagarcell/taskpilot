<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\RepositoryToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StoreProjectRepositoryBindingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * Logic: only members with project access can configure the canonical repository binding for the project.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $project = $this->route('project');

        if ($user === null || ! $project instanceof Project) {
            return false;
        }

        return $project->owner_id === $user->id
            || $project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     * Logic: validate the project repository binding shape before persisting the canonical project context.
     */
    public function rules(): array
    {
        return [
            'provider' => ['sometimes', 'string', 'max:255', 'in:github'],
            'binding_type' => ['sometimes', 'string', 'in:remote,local'],
            'remote_owner' => ['nullable', 'string', 'max:255'],
            'remote_repo' => ['nullable', 'string', 'max:255'],
            'remote_url' => ['nullable', 'url', 'max:255'],
            'local_path' => ['nullable', 'string', 'max:1024'],
            'default_branch' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Validate the remote GitHub target before saving the binding.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     * Logic: ensure a remote GitHub repository really exists before we persist the project binding and redirect the user back with a validation error.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $provider = strtolower((string) ($this->input('provider', 'github')));
            $bindingType = strtolower((string) ($this->input('binding_type', 'remote')));

            Log::info('Project repository binding validation started.', [
                'project_id' => $this->route('project')?->id,
                'provider' => $provider,
                'binding_type' => $bindingType,
                'remote_owner' => trim((string) $this->input('remote_owner', '')),
                'remote_repo' => trim((string) $this->input('remote_repo', '')),
                'remote_url' => $this->input('remote_url', ''),
            ]);

            if ($provider !== 'github' || $bindingType !== 'remote') {
                return;
            }

            $remoteOwner = trim((string) $this->input('remote_owner', ''));
            $remoteRepo = trim((string) $this->input('remote_repo', ''));

            if ($remoteOwner === '' || $remoteRepo === '') {
                return;
            }

            $baseUri = rtrim((string) config('services.github.base_uri', 'https://api.github.com'), '/');
            $project = $this->route('project');
            $requestClient = Http::accept('application/vnd.github+json')
                ->withHeaders([
                    'X-GitHub-Api-Version' => '2022-11-28',
                ]);

            if ($project instanceof Project) {
                $token = RepositoryToken::query()
                    ->where('project_id', $project->id)
                    ->where('provider', 'github')
                    ->where('user_id', $this->user()?->id)
                    ->orderByDesc('updated_at')
                    ->first();

                if ($token !== null && trim((string) $token->access_token) !== '') {
                    $requestClient = $requestClient->withToken((string) $token->access_token);
                }
            }

            $response = $requestClient->get(sprintf('%s/repos/%s/%s', $baseUri, $remoteOwner, $remoteRepo));

            if ($response->failed()) {
                Log::warning('Project repository binding GitHub validation failed.', [
                    'project_id' => $this->route('project')?->id,
                    'remote_owner' => $remoteOwner,
                    'remote_repo' => $remoteRepo,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'has_project_token' => $project instanceof Project && RepositoryToken::query()
                        ->where('project_id', $project->id)
                        ->where('provider', 'github')
                        ->where('user_id', $this->user()?->id)
                        ->exists(),
                ]);

                $validator->errors()->add(
                    'remote_repo',
                    'The GitHub repository is private, not accessible with the current project OAuth token or non existent. Connect GitHub OAuth for this project and try again.'
                );
                return;
            }

            Log::info('Project repository binding GitHub inspection succeeded.', [
                'project_id' => $this->route('project')?->id,
                'remote_owner' => $remoteOwner,
                'remote_repo' => $remoteRepo,
                'resolved_url' => $response->json('html_url') ?? sprintf('https://github.com/%s/%s', $remoteOwner, $remoteRepo),
            ]);
        });
    }
}
