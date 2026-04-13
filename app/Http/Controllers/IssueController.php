<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueStoreRequest;
use App\Http\Requests\IssueUpdateRequest;
use App\Models\Issue;
use App\Models\IssueFile;
use App\Models\IssueImage;
use App\Models\IssueLink;
use App\Models\Project;
use App\Services\IssueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function __construct(private readonly IssueService $issueService)
    {
    }

    public function index(Request $request): Response
    {
        $query = Issue::query()
            ->with(['project:id,name,client_id', 'project.client:id,name', 'parentIssue:id,title', 'images', 'files', 'links'])
            ->withCount(['subIssues', 'images', 'files'])
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
            'issues' => $query->paginate(10)->withQueryString(),
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
            ->with(['project:id,name,client_id', 'project.client:id,name', 'images', 'files', 'links'])
            ->withCount(['subIssues', 'images', 'files'])
            ->whereNull('parent_id')
            ->when($project, fn ($query) => $query->where('project_id', $project->id))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('status');

        return Inertia::render('Issues/Kanban', [
            'columns' => [
                'todo' => $issues->get('todo', collect())->values(),
                'inprogress' => $issues->get('inprogress', collect())->values(),
                'done' => $issues->get('done', collect())->sortByDesc('done_at')->values(),
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
        $parentIssue = $this->issueService->resolveParentIssue($validated['parent_id'] ?? null, $project->id);
        $returnToIssue = isset($validated['return_to_issue_id'])
            ? Issue::query()->where('project_id', $project->id)->find($validated['return_to_issue_id'])
            : null;

        $issue = Issue::query()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'done_at' => $validated['status'] === 'done' ? now() : null,
            'project_id' => $project->id,
            'parent_id' => $parentIssue?->id,
        ]);

        $this->issueService->storeAttachments($request, $issue);
        $this->issueService->syncLinks($validated['links'] ?? null, $issue);

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
        $issue->load($this->issueService->detailRelations());
        $this->issueService->loadNestedSubIssues($issue);

        return Inertia::render('Issues/Show', [
            'issue' => $issue,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'projectIssues' => $this->issueService->issueOptionsForProject($issue->project_id, [$issue->id]),
            'parentIssueOptions' => $this->issueService->parentIssueOptions($issue),
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
        $parentIssue = $this->issueService->resolveParentIssue($validated['parent_id'] ?? null, $project->id, $issue->id);

        $previousStatus = $issue->status;
        $nextStatus = $validated['status'];
        $doneAt = $issue->done_at;

        if ($nextStatus === 'done' && $previousStatus !== 'done') {
            $doneAt = now();
        } elseif ($nextStatus !== 'done') {
            $doneAt = null;
        }

        $issue->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $nextStatus,
            'done_at' => $doneAt,
            'project_id' => $project->id,
            'parent_id' => $parentIssue?->id,
        ]);

        $this->issueService->storeAttachments($request, $issue);
        $this->issueService->syncLinks($validated['links'] ?? null, $issue);

        return redirect()
            ->route('issues.show', $issue)
            ->with('success', "Issue {$issue->title} updated successfully.");
    }

    public function destroy(Issue $issue): RedirectResponse
    {
        foreach ($issue->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        foreach ($issue->files as $file) {
            Storage::disk('public')->delete($file->path);
        }

        $project = $issue->project;
        $title = $issue->title;
        $issue->delete();

        return redirect()
            ->route('projects.show', $project)
            ->with('success', "Issue {$title} deleted successfully.");
    }

    public function destroyImage(IssueImage $issueImage): RedirectResponse
    {
        Storage::disk('public')->delete($issueImage->path);
        $issueImage->delete();

        return back()->with('success', 'Image deleted.');
    }

    public function destroyFile(IssueFile $issueFile): RedirectResponse
    {
        Storage::disk('public')->delete($issueFile->path);
        $issueFile->delete();

        return back()->with('success', 'File deleted.');
    }

    public function destroyLink(IssueLink $issueLink): RedirectResponse
    {
        $issueLink->delete();

        return back()->with('success', 'Link deleted.');
    }
}
