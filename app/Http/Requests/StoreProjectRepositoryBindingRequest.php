<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

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
}
