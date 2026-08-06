<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIntegrationIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tag_names' => ['sometimes', 'array'],
            'tag_names.*' => ['nullable', 'string', 'max:50'],
            'links' => ['sometimes', 'array'],
            'links.*.url' => ['nullable', 'url', 'max:2048'],
            'links.*.label' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['todo', 'inprogress', 'done'])],
            'parent_id' => ['nullable', 'integer', Rule::exists('issues', 'id')],
            'source_tool' => ['required', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'external_source_id' => ['required', 'string', 'max:191'],
            'repository' => ['nullable', 'string', 'max:255'],
            'git_branch' => ['nullable', 'string', 'max:255'],
            'commit_hash' => ['nullable', 'string', 'max:255'],
        ];
    }
}
