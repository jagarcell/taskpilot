<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * Logic: confirm the current user is authenticated and is the project owner or an active project member before allowing a comment to be created on an issue within that project.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');
        $user = auth()->user();

        if (! $user || ! $project) {
            return false;
        }

        return $project->owner_id === $user->id
            || $project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     * Logic: require a meaningful comment body before persisting a discussion update.
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
