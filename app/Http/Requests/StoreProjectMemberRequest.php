<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * Logic: only the project owner can invite members to the project.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project !== null && $project->owner_id === auth()->id();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     * Logic: validate the invite payload before creating the membership and notifying the invited user.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['nullable', 'string', 'in:member,owner'],
        ];
    }
}
