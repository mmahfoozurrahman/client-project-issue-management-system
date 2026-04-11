<?php

namespace App\Http\Requests;

use App\Models\Issue;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueUpdateRequest extends FormRequest
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
        /** @var Issue $issue */
        $issue = $this->route('issue');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['todo', 'inprogress', 'done'])],
            'project_id' => ['required', 'integer', Rule::exists(Project::class, 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists(Issue::class, 'id'), Rule::notIn([$issue?->id])],
            'images' => ['sometimes', 'array'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png', 'max:10240'],
            'files' => ['sometimes', 'array'],
            'files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,rtf,ppt,pptx,zip,rar', 'max:10240'],
            'links' => ['sometimes', 'array'],
            'links.*.url' => ['nullable', 'string', 'max:2048'],
            'links.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'images.*' => 'issue image',
            'files.*' => 'issue file',
            'links.*.url' => 'issue link',
        ];
    }
}
