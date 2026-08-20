<?php

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * Logic: only authenticated users with access to a project may update issues inside it.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     * Logic: validate issue update payloads before persisting any edits.
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
        ];
    }
}
