<?php

namespace App\Http\Requests;

use App\Models\Issue;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['todo', 'inprogress', 'done'])],
            'project_id' => ['required', 'integer', Rule::exists(Project::class, 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists(Issue::class, 'id')],
            'return_to_issue_id' => ['nullable', 'integer', Rule::exists(Issue::class, 'id')],
            'images' => ['sometimes', 'array'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'images.*' => 'issue image',
        ];
    }
}
