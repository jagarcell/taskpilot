<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * Logic: owners may create labels for a project; members and guests cannot manage project metadata.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        return auth()->check() && $project !== null && $project->owner_id === auth()->id();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     * Logic: validate that the label name is present, trimmed, and unique within the project.
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                Rule::unique('labels', 'name')->where(fn ($query) => $query->where('project_id', $project?->id)),
            ],
        ];
    }
}
