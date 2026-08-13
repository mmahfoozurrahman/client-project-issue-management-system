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
use App\Models\ProjectMember;
use App\Models\SiteMeta;
use App\Services\IssueService;
use App\Services\RichTextSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function __construct(
        private readonly IssueService $issueService,
        private readonly RichTextSanitizer $richTextSanitizer,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $accessibleIds = $user->accessibleProjectIds();

        $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'status' => ['nullable', 'string', Rule::in(['todo', 'inprogress', 'done'])],
            'tag_id' => ['nullable', 'integer', Rule::exists('issue_tags', 'id')],
            'q' => ['nullable', 'string', 'max:255'],
            'at_risk' => ['nullable', 'boolean'],
        ]);

        $staleDays = max((int) SiteMeta::value('issue_stale_days', (string) config('app.issue_stale_days', 3)), 1);

        $query = Issue::withoutGlobalScope('user_owned')
            ->whereIn('project_id', $accessibleIds)
            ->with([
                'project:id,name,client_id',
                'project.client' => function ($query) {
                    $query->withoutGlobalScope('user_owned')->select('id', 'name');
                },
                'parentIssue:id,title',
                'images',
                'files',
                'links',
                'tags'
            ])
            ->withCount(['subIssues', 'images', 'files'])
            ->withExists(['pinnedByUsers as is_pinned' => fn ($pinQuery) => $pinQuery->whereKey($user->id)])
            ->whereNull('parent_id')
            ->latest();

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            abort_unless(in_array($projectId, $accessibleIds), 403);
            $query->where('project_id', $projectId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('tag_id')) {
            $tagId = (int) $request->input('tag_id');
            $query->whereHas('tags', fn ($q) => $q->whereKey($tagId));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(
                fn ($q) => $q
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhereHas('links', fn ($linkQuery) => $linkQuery
                        ->where('url', 'like', "%{$term}%")
                        ->orWhere('label', 'like', "%{$term}%"))
            );
        }

        if ((bool) $request->input('at_risk')) {
            $query
                ->where('status', '!=', 'done')
                ->where('updated_at', '<=', Carbon::now()->subDays($staleDays));
        }

        $tagQuery = IssueTag::withoutGlobalScope('user_owned')->whereIn('project_id', $accessibleIds)->orderBy('name');
        if ($request->filled('project_id')) {
            $tagQuery->where('project_id', (int) $request->input('project_id'));
        }

        $accessibleProjects = Project::withoutGlobalScope('user_owned')
            ->whereIn('id', $accessibleIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $issueSearchSuggestions = Issue::withoutGlobalScope('user_owned')
            ->whereIn('project_id', $accessibleIds)
            ->whereNull('parent_id')
            ->with([
                'project:id,name',
                'links:id,issue_id,url,label',
            ])
            ->latest()
            ->get(['id', 'project_id', 'title']);

        return Inertia::render('Issues/Index', [
            'issues' => $query->paginate(10)->withQueryString(),
            'projects' => $accessibleProjects,
            'issueTags' => $tagQuery->get(['id', 'name', 'project_id']),
            'issueSearchSuggestions' => $issueSearchSuggestions,
            'filters' => [
                'project_id' => $request->input('project_id'),
                'status' => $request->input('status'),
                'tag_id' => $request->input('tag_id'),
                'q' => $request->input('q'),
                'at_risk' => (bool) $request->input('at_risk'),
            ],
            'staleDays' => $staleDays,
            'canCreateIssue' => $user->is_admin || ProjectMember::where('user_id', $user->id)
                ->whereHas('role.permissions', fn ($q) => $q->where('slug', 'issue.create'))
                ->exists(),
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Issues'],
            ],
        ]);
    }

    public function kanban(Request $request): Response
    {
        $user = $request->user();
        $accessibleIds = $user->accessibleProjectIds();

        $project = null;
        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            abort_unless(in_array($projectId, $accessibleIds), 403);
            $project = Project::withoutGlobalScope('user_owned')->findOrFail($projectId);
        }

        $issues = Issue::withoutGlobalScope('user_owned')
            ->whereIn('project_id', $accessibleIds)
            ->with([
                'project:id,name,client_id',
                'project.client' => function ($query) {
                    $query->withoutGlobalScope('user_owned')->select('id', 'name');
                },
                'parentIssue:id,title',
                'images',
                'files',
                'links',
                'tags'
            ])
            ->withCount(['subIssues', 'images', 'files'])
            ->whereNull('parent_id')
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('status');

        $dailyTarget = max((int) SiteMeta::value('issue_daily_target', (string) config('app.issue_daily_target', 3)), 1);
        $staleDays = max((int) SiteMeta::value('issue_stale_days', (string) config('app.issue_stale_days', 3)), 1);
        $completedToday = Issue::withoutGlobalScope('user_owned')
            ->whereIn('project_id', $accessibleIds)
            ->whereDate('done_at', Carbon::today())
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            ->count();

        return Inertia::render('Issues/Kanban', [
            'columns' => [
                'todo' => $issues->get('todo', collect())->values(),
                'inprogress' => $issues->get('inprogress', collect())->values(),
                'done' => $issues->get('done', collect())->sortByDesc('updated_at')->values(),
            ],
            'todayTarget' => [
                'target' => $dailyTarget,
                'completed' => $completedToday,
                'remaining' => max($dailyTarget - $completedToday, 0),
            ],
            'laneNudges' => [
                'todo' => $issues->get('todo', collect())->filter(fn (Issue $i) => $i->updated_at <= Carbon::now()->subDays($staleDays))->count(),
                'inprogress' => $issues->get('inprogress', collect())->filter(fn (Issue $i) => $i->updated_at <= Carbon::now()->subDays($staleDays))->count(),
            ],
            'staleDays' => $staleDays,
            'projects' => Project::withoutGlobalScope('user_owned')->whereIn('id', $accessibleIds)->orderBy('name')->get(['id', 'name']),
            'filters' => ['project_id' => $request->input('project_id')],
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Kanban'],
            ],
        ]);
    }

    public function dailyActivity(Request $request): Response
    {
        $user = $request->user();
        $accessibleIds = $user->accessibleProjectIds();

        $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'date' => ['nullable', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'string', Rule::in(['todo', 'inprogress', 'done'])],
        ]);

        $selectedDate = $request->filled('date') ? Carbon::parse((string) $request->input('date'))->toDateString() : Carbon::today()->toDateString();
        $selectedMonth = $request->filled('month') ? (string) $request->input('month') : Carbon::parse($selectedDate)->format('Y-m');
        $selectedProjectId = $request->filled('project_id') ? (int) $request->input('project_id') : null;
        $selectedStatus = $request->filled('status') ? (string) $request->input('status') : 'inprogress';

        if ($selectedProjectId) {
            abort_unless(in_array($selectedProjectId, $accessibleIds), 403);
        }

        $monthStart = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $monthEnd = (clone $monthStart)->endOfMonth();

        $base = fn () => Issue::withoutGlobalScope('user_owned')
            ->whereIn('project_id', $accessibleIds)
            ->whereDate('created_at', $selectedDate)
            ->when($selectedProjectId, fn ($q) => $q->where('project_id', $selectedProjectId));

        $calendarCounts = Issue::withoutGlobalScope('user_owned')
            ->whereIn('project_id', $accessibleIds)
            ->selectRaw('DATE(created_at) as date_key, COUNT(*) as total')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->when($selectedProjectId, fn ($q) => $q->where('project_id', $selectedProjectId))
            ->groupBy('date_key')
            ->pluck('total', 'date_key');

        $issuesQuery = $base()
            ->with([
                'project:id,name,client_id',
                'project.client' => function ($query) {
                    $query->withoutGlobalScope('user_owned')->select('id', 'name');
                },
                'parentIssue:id,title',
                'images',
                'files',
                'links',
                'tags'
            ])
            ->withCount(['subIssues', 'images', 'files'])
            ->where('status', $selectedStatus);

        $selectedStatus === 'done'
            ? $issuesQuery->orderByDesc('updated_at')
            : $issuesQuery->orderByDesc('created_at');

        $staleDays = max((int) SiteMeta::value('issue_stale_days', (string) config('app.issue_stale_days', 3)), 1);

        $carryoverIssues = Issue::withoutGlobalScope('user_owned')
            ->whereIn('project_id', $accessibleIds)
            ->with(['project:id,name,client_id', 'project.client:id,name', 'parentIssue:id,title', 'images', 'files', 'links', 'tags'])
            ->withCount(['subIssues', 'images', 'files'])
            ->whereDate('created_at', '<', $selectedDate)
            ->where('status', '!=', 'done')
            ->where('updated_at', '<=', Carbon::now()->subDays($staleDays))
            ->when($selectedProjectId, fn ($q) => $q->where('project_id', $selectedProjectId))
            ->orderBy('updated_at')
            ->limit(6)
            ->get();

        $statusCounts = collect(['todo', 'inprogress', 'done'])->mapWithKeys(
            fn (string $status) => [$status => $base()->where('status', $status)->count()]
        );

        return Inertia::render('Issues/DailyActivity', [
            'issues' => $issuesQuery->get(),
            'statusCounts' => $statusCounts,
            'summary' => [
                'created_total' => $base()->count(),
                'completed_total' => Issue::withoutGlobalScope('user_owned')
                    ->whereIn('project_id', $accessibleIds)
                    ->whereDate('done_at', $selectedDate)
                    ->when($selectedProjectId, fn ($q) => $q->where('project_id', $selectedProjectId))
                    ->count(),
            ],
            'carryoverIssues' => $carryoverIssues,
            'projects' => Project::withoutGlobalScope('user_owned')->whereIn('id', $accessibleIds)->orderBy('name')->get(['id', 'name']),
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
        $accessibleIds = $request->user()->accessibleProjectIds();

        $project = Project::withoutGlobalScope('user_owned')->findOrFail($validated['project_id']);
        abort_unless(in_array($project->id, $accessibleIds), 403);
        $this->authorize('create', [Issue::class, $project]);

        $parentIssue = $this->issueService->resolveParentIssue($validated['parent_id'] ?? null, $project->id);
        $returnToIssue = isset($validated['return_to_issue_id'])
            ? Issue::withoutGlobalScope('user_owned')->where('project_id', $project->id)->find($validated['return_to_issue_id'])
            : null;

        $issue = Issue::query()->create([
            'title' => $validated['title'],
            'description' => $this->richTextSanitizer->sanitize($validated['description'] ?? null),
            'status' => $validated['status'],
            'done_at' => $validated['status'] === 'done' ? now() : null,
            'project_id' => $project->id,
            'parent_id' => $parentIssue?->id,
        ]);

        $this->issueService->storeAttachments($request, $issue);
        $this->issueService->syncLinks($validated['links'] ?? null, $issue);
        $this->issueService->syncTags($validated['tag_names'] ?? null, $issue, $project->id);

        if ($returnToIssue) {
            return redirect()->route('issues.show', $returnToIssue)->with('success', "Sub-issue {$issue->title} created successfully.");
        }

        return redirect()->route('issues.show', $issue)->with('success', "Issue {$issue->title} created successfully.");
    }

    public function show(Issue $issue): Response
    {
        $this->authorize('view', $issue);

        $issue->load($this->issueService->detailRelations());
        $this->issueService->loadNestedSubIssues($issue);
        $issue->setAttribute('is_pinned', $issue->pinnedByUsers()->whereKey(auth()->id())->exists());

        $accessibleIds = auth()->user()->accessibleProjectIds();
        $issueTagIds = $issue->tags->pluck('id');
        $matchingIssues = collect();

        if ($issueTagIds->isNotEmpty()) {
            $matchingIssues = Issue::withoutGlobalScope('user_owned')
                ->where('project_id', $issue->project_id)
                ->whereKeyNot($issue->id)
                ->whereHas('tags', fn ($query) => $query->whereIn('issue_tags.id', $issueTagIds))
                ->with('tags:id,name')
                ->get(['id', 'title', 'status', 'updated_at'])
                ->map(function (Issue $matchingIssue) use ($issueTagIds) {
                    $matchingTags = $matchingIssue->tags
                        ->whereIn('id', $issueTagIds)
                        ->values();

                    return [
                        'id' => $matchingIssue->id,
                        'title' => $matchingIssue->title,
                        'status' => $matchingIssue->status,
                        'matching_tag_count' => $matchingTags->count(),
                        'matching_tags' => $matchingTags->map(fn (IssueTag $tag) => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                        ]),
                    ];
                })
                ->values();

            $matchingIssues = $this->issueService->sortMatchingIssues($matchingIssues);
        }

        return Inertia::render('Issues/Show', [
            'issue' => $issue,
            'matchingIssues' => $matchingIssues,
            'projects' => Project::withoutGlobalScope('user_owned')->whereIn('id', $accessibleIds)->orderBy('name')->get(['id', 'name']),
            'projectIssues' => $this->issueService->issueOptionsForProject($issue->project_id, [$issue->id]),
            'parentIssueOptions' => $this->issueService->parentIssueOptions($issue),
            'projectTags' => IssueTag::withoutGlobalScope('user_owned')
                ->where('project_id', $issue->project_id)
                ->orderBy('name')
                ->get(['id', 'name', 'project_id']),
            'userProjectRole' => auth()->user()->projectRoleOn($issue->project_id),
            'canCreate' => auth()->user()->canOnProject('issue.create', $issue->project_id),
            'canEdit' => auth()->user()->canOnProject('issue.edit', $issue->project_id),
            'canDelete' => auth()->user()->canOnProject('issue.delete', $issue->project_id),
            'canChangeStatus' => auth()->user()->canOnProject('issue.change_status', $issue->project_id),
            'canUploadAttachment' => auth()->user()->canOnProject('issue.upload_attachment', $issue->project_id),
            'canDeleteAttachment' => auth()->user()->canOnProject('issue.delete_attachment', $issue->project_id),
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
        $this->authorize('update', $issue);

        $validated = $request->validated();
        $accessibleIds = $request->user()->accessibleProjectIds();

        $project = Project::withoutGlobalScope('user_owned')->findOrFail($validated['project_id']);
        abort_unless(in_array($project->id, $accessibleIds), 403);

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
            'description' => $this->richTextSanitizer->sanitize($validated['description'] ?? null),
            'status' => $nextStatus,
            'done_at' => $doneAt,
            'project_id' => $project->id,
            'parent_id' => $parentIssue?->id,
        ]);

        $this->issueService->storeAttachments($request, $issue);
        $this->issueService->syncLinks($validated['links'] ?? null, $issue);
        $this->issueService->syncTags($validated['tag_names'] ?? null, $issue, $project->id);

        return redirect()->route('issues.show', $issue)->with('success', "Issue {$issue->title} updated successfully.");
    }

    public function destroy(Issue $issue): RedirectResponse
    {
        $this->authorize('delete', $issue);

        foreach ($issue->images as $image) {
            Storage::disk('public')->delete($image->path);
        }
        foreach ($issue->files as $file) {
            Storage::disk('public')->delete($file->path);
        }

        $project = $issue->project;
        $title = $issue->title;
        $issue->delete();

        return redirect()->route('projects.show', $project)->with('success', "Issue {$title} deleted successfully.");
    }

    public function togglePin(Request $request, Issue $issue): RedirectResponse
    {
        $this->authorize('view', $issue);

        $pins = $request->user()->pinnedIssues();
        $isPinned = $pins->whereKey($issue->id)->exists();

        if ($isPinned) {
            $pins->detach($issue->id);
        } else {
            $pins->attach($issue->id);
        }

        return back()->with('success', $isPinned ? 'Issue unpinned.' : 'Issue pinned.');
    }

    public function destroyImage(IssueImage $issueImage): RedirectResponse
    {
        $issue = Issue::withoutGlobalScope('user_owned')->findOrFail($issueImage->issue_id);
        $this->authorize('deleteAttachment', $issue);

        Storage::disk('public')->delete($issueImage->path);
        $issueImage->delete();

        return back()->with('success', 'Image deleted.');
    }

    public function destroyFile(IssueFile $issueFile): RedirectResponse
    {
        $issue = Issue::withoutGlobalScope('user_owned')->findOrFail($issueFile->issue_id);
        $this->authorize('deleteAttachment', $issue);

        Storage::disk('public')->delete($issueFile->path);
        $issueFile->delete();

        return back()->with('success', 'File deleted.');
    }

    public function destroyLink(IssueLink $issueLink): RedirectResponse
    {
        $issue = Issue::withoutGlobalScope('user_owned')->findOrFail($issueLink->issue_id);
        $this->authorize('deleteAttachment', $issue);

        $issueLink->delete();

        return back()->with('success', 'Link deleted.');
    }

    public function updateStatus(Request $request, Issue $issue): RedirectResponse
    {
        $this->authorize('changeStatus', $issue);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['todo', 'inprogress', 'done'])],
        ]);

        $doneAt = $issue->done_at;
        if ($validated['status'] === 'done' && $issue->status !== 'done') {
            $doneAt = now();
        } elseif ($validated['status'] !== 'done') {
            $doneAt = null;
        }

        $issue->update(['status' => $validated['status'], 'done_at' => $doneAt]);

        return back()->with('success', 'Status updated.');
    }
}
