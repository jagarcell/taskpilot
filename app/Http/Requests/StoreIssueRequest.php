<?php

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * Logic: only authenticated users may create issues for a project they belong to.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     * Logic: validate the issue payload so only supported types, priorities, and workflow states can be created.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(array_map(fn ($case) => $case->value, IssueType::cases()))],
            'priority' => ['required', Rule::in(array_map(fn ($case) => $case->value, IssuePriority::cases()))],
            'status' => ['required', Rule::in(array_map(fn ($case) => $case->value, IssueStatus::cases()))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['integer', 'exists:labels,id'],
        ];
    }
}
