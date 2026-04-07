<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueStoreRequest;
use App\Http\Requests\IssueUpdateRequest;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Issue::query()
            ->with(['project:id,name', 'images'])
            ->withCount(['subIssues', 'images'])
            ->whereNull('parent_id')
            ->latest();

        if ($request->filled('project_id')) {
            $project = Project::query()->findOrFail((int) $request->input('project_id'));
            $query->where('project_id', $project->id);
        }

        if ($request->filled('status')) {
            $request->validate([
                'status' => ['string', Rule::in(['todo', 'inprogress', 'done'])],
            ]);

            $query->where('status', $request->string('status')->value());
        }

        return Inertia::render('Issues/Index', [
            'issues' => $query->get(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'project_id' => $request->input('project_id'),
                'status' => $request->input('status'),
            ],
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Issues'],
            ],
        ]);
    }

    public function kanban(Request $request): Response
    {
        $project = null;

        if ($request->filled('project_id')) {
            $project = Project::query()->findOrFail((int) $request->input('project_id'));
        }

        $issues = Issue::query()
            ->with(['project:id,name', 'images'])
            ->withCount(['subIssues', 'images'])
            ->whereNull('parent_id')
            ->when($project, fn ($query) => $query->where('project_id', $project->id))
            ->orderBy('created_at')
            ->get()
            ->groupBy('status');

        return Inertia::render('Issues/Kanban', [
            'columns' => [
                'todo' => $issues->get('todo', collect())->values(),
                'inprogress' => $issues->get('inprogress', collect())->values(),
                'done' => $issues->get('done', collect())->values(),
            ],
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'project_id' => $request->input('project_id'),
            ],
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Kanban'],
            ],
        ]);
    }

    public function store(IssueStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $project = Project::query()->findOrFail($validated['project_id']);
        $parentIssue = $this->resolveParentIssue($validated['parent_id'] ?? null, $project->id);
        $returnToIssue = isset($validated['return_to_issue_id'])
            ? Issue::query()->where('project_id', $project->id)->find($validated['return_to_issue_id'])
            : null;

        $issue = Issue::query()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'project_id' => $project->id,
            'parent_id' => $parentIssue?->id,
        ]);

        $this->storeImages($request, $issue);

        if ($returnToIssue) {
            return redirect()
                ->route('issues.show', $returnToIssue)
                ->with('success', "Sub-issue {$issue->title} created successfully.");
        }

        return redirect()
            ->route('issues.show', $issue)
            ->with('success', "Issue {$issue->title} created successfully.");
    }

    public function show(Issue $issue): Response
    {
        $issue->load($this->detailRelations());
        $this->loadNestedSubIssues($issue);

        return Inertia::render('Issues/Show', [
            'issue' => $issue,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'projectIssues' => Issue::query()
                ->where('project_id', $issue->project_id)
                ->whereKeyNot($issue->id)
                ->orderBy('title')
                ->get(['id', 'title']),
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Projects', 'href' => route('projects.index')],
                ['label' => $issue->project->name, 'href' => route('projects.show', $issue->project)],
                ['label' => $issue->title],
            ],
        ]);
    }

    public function update(IssueUpdateRequest $request, Issue $issue): RedirectResponse
    {
        $validated = $request->validated();

        $project = Project::query()->findOrFail($validated['project_id']);
        $parentIssue = $this->resolveParentIssue($validated['parent_id'] ?? null, $project->id, $issue->id);

        $issue->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'project_id' => $project->id,
            'parent_id' => $parentIssue?->id,
        ]);

        $this->storeImages($request, $issue);

        return redirect()
            ->route('issues.show', $issue)
            ->with('success', "Issue {$issue->title} updated successfully.");
    }

    public function destroy(Issue $issue): RedirectResponse
    {
        foreach ($issue->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $project = $issue->project;
        $title = $issue->title;
        $issue->delete();

        return redirect()
            ->route('projects.show', $project)
            ->with('success', "Issue {$title} deleted successfully.");
    }

    private function detailRelations(): array
    {
        return [
            'project:id,name',
            'images',
            'parentIssue:id,title,project_id,parent_id',
            'subIssues' => fn ($query) => $query
                ->with(['images', 'project:id,name'])
                ->withCount(['subIssues', 'images'])
                ->latest(),
        ];
    }

    private function resolveParentIssue(?int $parentId, int $projectId, ?int $ignoreIssueId = null): ?Issue
    {
        if (! $parentId) {
            return null;
        }

        $query = Issue::query()
            ->where('project_id', $projectId)
            ->whereKey($parentId);

        if ($ignoreIssueId) {
            $query->whereKeyNot($ignoreIssueId);
        }

        return $query->firstOrFail();
    }

    private function storeImages(Request $request, Issue $issue): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        foreach ($request->file('images') as $file) {
            $path = $file->store('issues', 'public');

            $issue->images()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    private function loadNestedSubIssues(Issue $issue): void
    {
        $issue->load([
            'subIssues' => fn ($query) => $query
                ->with(['images', 'project:id,name', 'parentIssue:id,title,project_id,parent_id'])
                ->withCount(['subIssues', 'images'])
                ->latest(),
        ]);

        $issue->subIssues->each(fn (Issue $subIssue) => $this->loadNestedSubIssues($subIssue));
    }
}
