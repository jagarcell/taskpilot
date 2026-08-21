<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * Logic: only the project owner can update label metadata.
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
     * Logic: accept a label name that is unique within the project while ignoring the existing record being edited.
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $label = $this->route('label');

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                Rule::unique('labels', 'name')
                    ->where(fn ($query) => $query->where('project_id', $project?->id))
                    ->ignore($label?->id),
            ],
        ];
    }
}
