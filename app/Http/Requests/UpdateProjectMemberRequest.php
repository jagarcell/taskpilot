<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * Logic: only the project owner may update a member's role, and the current owner is protected.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');
        $projectMember = $this->route('projectMember');

        return $project !== null
            && $projectMember !== null
            && $project->owner_id === auth()->id()
            && $projectMember->project_id === $project->id
            && $projectMember->user_id !== $project->owner_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     * Logic: validate the role change before modifying the membership assignment.
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:member,owner'],
        ];
    }
}
