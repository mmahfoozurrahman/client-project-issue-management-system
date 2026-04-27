<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueStoreRequest;
use App\Http\Requests\IssueUpdateRequest;
use App\Models\Issue;
use App\Models\IssueFile;
use App\Models\IssueImage;
use App\Models\IssueLink;
use App\Models\IssueTag;
use App\Models\Project;
use App\Models\SiteMeta;
use App\Services\IssueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists(Project::class, 'id')],
            'status' => ['nullable', 'string', Rule::in(['todo', 'inprogress', 'done'])],
            'tag_id' => ['nullable', 'integer', Rule::exists(IssueTag::class, 'id')],
            'q' => ['nullable', 'string', 'max:255'],
            'at_risk' => ['nullable', 'boolean'],
        ]);

        $staleDays = max((int) SiteMeta::value('issue_stale_days', (string) config('app.issue_stale_days', 7)), 1);
        $query = Issue::query()
            ->with(['project:id,name,client_id', 'project.client:id,name', 'parentIssue:id,title', 'images', 'files', 'links', 'tags'])
            ->withCount(['subIssues', 'images', 'files'])
            ->whereNull('parent_id')
            ->latest();

        if ($request->filled('project_id')) {
            $project = Project::query()->findOrFail((int) $request->input('project_id'));
            $query->where('project_id', $project->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('tag_id')) {
            $tagId = (int) $request->input('tag_id');
            $query->whereHas('tags', fn ($tagQuery) => $tagQuery->whereKey($tagId));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));

            $query->where(function ($searchQuery) use ($term) {
                $searchQuery
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ((bool) $request->input('at_risk')) {
            $query
                ->where('status', '!=', 'done')
                ->where('updated_at', '<=', Carbon::now()->subDays($staleDays));
        }

        $tagQuery = IssueTag::query()->orderBy('name');
        if ($request->filled('project_id')) {
            $tagQuery->where('project_id', (int) $request->input('project_id'));
        }

        return Inertia::render('Issues/Index', [
            'issues' => $query->paginate(10)->withQueryString(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'issueTags' => $tagQuery->get(['id', 'name', 'project_id']),
            'filters' => [
                'project_id' => $request->input('project_id'),
                'status' => $request->input('status'),
                'tag_id' => $request->input('tag_id'),
                'q' => $request->input('q'),
                'at_risk' => (bool) $request->input('at_risk'),
            ],
            'staleDays' => $staleDays,
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
            ->with(['project:id,name,client_id', 'project.client:id,name', 'images', 'files', 'links', 'tags'])
            ->withCount(['subIssues', 'images', 'files'])
            ->whereNull('parent_id')
            ->when($project, fn ($query) => $query->where('project_id', $project->id))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('status');

        $dailyTarget = max((int) SiteMeta::value('issue_daily_target', (string) config('app.issue_daily_target', 3)), 1);
        $staleDays = max((int) SiteMeta::value('issue_stale_days', (string) config('app.issue_stale_days', 7)), 1);
        $completedToday = Issue::query()
            ->whereDate('done_at', Carbon::today())
            ->when($project, fn ($query) => $query->where('project_id', $project->id))
            ->count();

        return Inertia::render('Issues/Kanban', [
            'columns' => [
                'todo' => $issues->get('todo', collect())->values(),
                'inprogress' => $issues->get('inprogress', collect())->values(),
                'done' => $issues->get('done', collect())->sortByDesc('done_at')->values(),
            ],
            'todayTarget' => [
                'target' => max($dailyTarget, 1),
                'completed' => $completedToday,
                'remaining' => max(max($dailyTarget, 1) - $completedToday, 0),
            ],
            'laneNudges' => [
                'todo' => ($issues->get('todo', collect()))
                    ->filter(fn (Issue $issue) => $issue->updated_at <= Carbon::now()->subDays($staleDays))
                    ->count(),
                'inprogress' => ($issues->get('inprogress', collect()))
                    ->filter(fn (Issue $issue) => $issue->updated_at <= Carbon::now()->subDays($staleDays))
                    ->count(),
            ],
            'staleDays' => $staleDays,
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

    public function dailyActivity(Request $request): Response
    {
        $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists(Project::class, 'id')],
            'date' => ['nullable', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'string', Rule::in(['todo', 'inprogress', 'done'])],
        ]);

        $selectedDate = $request->filled('date')
            ? Carbon::parse((string) $request->input('date'))->toDateString()
            : Carbon::today()->toDateString();
        $selectedMonth = $request->filled('month')
            ? (string) $request->input('month')
            : Carbon::parse($selectedDate)->format('Y-m');

        $selectedProjectId = $request->filled('project_id') ? (int) $request->input('project_id') : null;
        $selectedStatus = $request->filled('status') ? (string) $request->input('status') : 'inprogress';
        $monthStart = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $monthEnd = (clone $monthStart)->endOfMonth();

        $baseCreatedQuery = Issue::query()
            ->whereDate('created_at', $selectedDate)
            ->when($selectedProjectId, fn ($query) => $query->where('project_id', $selectedProjectId));

        $calendarCounts = Issue::query()
            ->selectRaw('DATE(created_at) as date_key, COUNT(*) as total')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->when($selectedProjectId, fn ($query) => $query->where('project_id', $selectedProjectId))
            ->groupBy('date_key')
            ->pluck('total', 'date_key');

        $issues = (clone $baseCreatedQuery)
            ->with(['project:id,name,client_id', 'project.client:id,name', 'tags'])
            ->where('status', $selectedStatus)
            ->orderByDesc('created_at')
            ->get();

        $staleDays = max((int) SiteMeta::value('issue_stale_days', (string) config('app.issue_stale_days', 7)), 1);
        $carryoverIssues = Issue::query()
            ->with(['project:id,name,client_id', 'project.client:id,name', 'tags'])
            ->whereDate('created_at', '<', $selectedDate)
            ->where('status', '!=', 'done')
            ->where('updated_at', '<=', Carbon::now()->subDays($staleDays))
            ->when($selectedProjectId, fn ($query) => $query->where('project_id', $selectedProjectId))
            ->orderBy('updated_at')
            ->limit(6)
            ->get();

        $statusCounts = collect(['todo', 'inprogress', 'done'])->mapWithKeys(
            fn (string $status) => [$status => (clone $baseCreatedQuery)->where('status', $status)->count()]
        );

        return Inertia::render('Issues/DailyActivity', [
            'issues' => $issues,
            'statusCounts' => $statusCounts,
            'summary' => [
                'created_total' => (clone $baseCreatedQuery)->count(),
                'completed_total' => Issue::query()
                    ->whereDate('done_at', $selectedDate)
                    ->when($selectedProjectId, fn ($query) => $query->where('project_id', $selectedProjectId))
                    ->count(),
            ],
            'carryoverIssues' => $carryoverIssues,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'project_id' => $selectedProjectId,
                'date' => $selectedDate,
                'month' => $selectedMonth,
                'status' => $selectedStatus,
            ],
            'calendar' => [
                'month' => $selectedMonth,
                'counts' => $calendarCounts,
            ],
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Issues', 'href' => route('issues.index')],
                ['label' => 'Daily Activity'],
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
        $this->issueService->syncTags($validated['tag_names'] ?? null, $issue, $project->id);

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
            'projectTags' => IssueTag::query()
                ->where('project_id', $issue->project_id)
                ->orderBy('name')
                ->get(['id', 'name', 'project_id']),
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
        $this->issueService->syncTags($validated['tag_names'] ?? null, $issue, $project->id);

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
