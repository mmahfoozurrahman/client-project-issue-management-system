<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Models\Client;
use App\Models\IssueTag;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Role;
use App\Models\User;
use App\Services\RichTextSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly RichTextSanitizer $richTextSanitizer)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless(auth()->user()->canAccessProjectsPage(), 403);

        $accessibleIds = auth()->user()->accessibleProjectIds();
        $search = trim((string) $request->input('q', ''));

        $projects = Project::withoutGlobalScope('user_owned')
            ->whereIn('id', $accessibleIds)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($clientQuery) use ($search) {
                            $clientQuery
                                ->withoutGlobalScope('user_owned')
                                ->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->with([
                'client' => function ($query) {
                    $query->withoutGlobalScope('user_owned')->select('id', 'name');
                }
            ])
            ->withCount('issues')
            ->latest()
            ->paginate(10)
            ->withQueryString();


        // ২. প্রতিটি প্রজেক্টের সাথে ইউজারের edit ও delete এর পারমিশন যোগ করে দেওয়া
        $projects->getCollection()->transform(function ($project) {
            $project->can_edit = auth()->user()->canOnProject('project.edit', $project->id);
            $project->can_delete = auth()->user()->canOnProject('project.delete', $project->id);
            return $project;
        });

        // ৩. অ্যাডমিন বা যার ক্রিয়েট করার পারমিশন আছে তাকে চেক করা
        $canCreateProject = auth()->user()->is_admin || ProjectMember::where('user_id', auth()->id())
            ->whereHas('role.permissions', fn($q) => $q->where('slug', 'project.create'))
            ->exists();
        //dd($canCreateProject);
        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'clients' => auth()->user()->is_admin
                ? Client::withoutGlobalScope('user_owned')->orderBy('name')->get(['id', 'name'])
                : Client::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'q' => $search,
            ],
            'canCreateProject' => $canCreateProject,
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Projects'],
            ],
        ]);
    }

    public function store(ProjectStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $client = Client::query()->findOrFail($validated['client_id']);

        $project = Project::query()->create([
            'name' => $validated['name'],
            'description' => $this->richTextSanitizer->sanitize($validated['description'] ?? null),
            'client_id' => $client->id,
        ]);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'role_id' => Role::where('slug', 'owner')->value('id'),
        ]);

        return redirect()
            ->route('projects.index')
            ->with('success', "Project {$project->name} created successfully.");
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $request->validate([
            'status' => ['nullable', 'string', Rule::in(['todo', 'inprogress', 'done'])],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('issue_tags', 'id')->where(fn($query) => $query->where('project_id', $project->id)),
            ],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $tagIds = collect($request->input('tag_ids', []))
            ->map(fn ($tagId) => (int) $tagId)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $search = trim((string) $request->input('q', ''));

        $project->load(['client:id,name']);

        $issues = $project->issues()
            ->whereNull('parent_id')
            ->when(
                $request->filled('status'),
                fn($query) => $query->where('status', $request->string('status')->value())
            )
            ->when(
                $tagIds,
                fn($query) => $query->whereHas(
                    'tags',
                    fn($tagQuery) => $tagQuery->whereIn('issue_tags.id', $tagIds),
                    '=',
                    count($tagIds)
                )
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->with(['images', 'files', 'links', 'tags', 'user:id,name', 'parentIssue:id,title'])
            ->withCount(['subIssues', 'images', 'files'])
            ->withExists(['pinnedByUsers as is_pinned' => fn ($pinQuery) => $pinQuery->whereKey(auth()->id())])
            ->paginate(10)
            ->withQueryString();

        $user = auth()->user();
        $userRole = $user->projectRoleOn($project->id);

        $pinnedIssues = $user->pinnedIssues()
            ->withoutGlobalScope('user_owned')
            ->where('project_id', $project->id)
            ->with(['images', 'files', 'links', 'tags:id,name', 'user:id,name', 'parentIssue:id,title'])
            ->withCount(['subIssues', 'images', 'files'])
            ->orderByPivot('created_at', 'desc')
            ->get();

        $projectMembers = $project->projectMembers()
            ->with(['user:id,name,email', 'role:id,name,slug'])
            ->get();

        $existingUserIds = $projectMembers->pluck('user_id');
        $addableUsers = User::where('is_admin', false)
            ->whereNotIn('id', $existingUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'issues' => $issues,
            'pinnedIssues' => $pinnedIssues,
            'projectTags' => IssueTag::query()
                ->where('project_id', $project->id)
                ->orderBy('name')
                ->get(['id', 'name', 'project_id']),
            'filters' => [
                'status' => $request->input('status'),
                'tag_ids' => $tagIds,
                'q' => $search,
            ],
            'userRole' => $userRole,
            'canManageMembers' => $user->canOnProject('project.manage_members', $project->id),
            'canCreateIssue' => $user->canOnProject('issue.create', $project->id),
            'projectMembers' => $projectMembers,    
            'addableUsers' => $addableUsers,
            //'roles' => Role::orderBy('name')->get(['id', 'name', 'slug']),
            // ১. roles এর জায়গায় এই কোডটি দিন
            'roles' => auth()->user()->is_admin
                ? Role::orderBy('name')->get(['id', 'name', 'slug'])
                : Role::where('slug', 'developer')->get(['id', 'name', 'slug']),
            // ২. নতুন এই ভেরিয়েবলটি যুক্ত করুন
            'canEditRoles' => auth()->user()->is_admin,
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Projects', 'href' => route('projects.index')],
                ['label' => $project->name],
            ],
        ]);
    }

    public function update(ProjectUpdateRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();
        $client = Client::query()->findOrFail($validated['client_id']);

        $project->update([
            'name' => $validated['name'],
            'description' => $this->richTextSanitizer->sanitize($validated['description'] ?? null),
            'client_id' => $client->id,
        ]);

        return redirect()
            ->route('projects.index')
            ->with('success', "Project {$project->name} updated successfully.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $name = $project->name;
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', "Project {$name} deleted successfully.");
    }
}
