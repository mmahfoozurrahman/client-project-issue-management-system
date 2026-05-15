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
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->canAccessProjectsPage(), 403);

        $accessibleIds = auth()->user()->accessibleProjectIds();

        $projects = Project::withoutGlobalScope('user_owned')
            ->whereIn('id', $accessibleIds)
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
            'description' => $validated['description'] ?? null,
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

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load(['client:id,name']);

        $issues = $project->issues()
            ->whereNull('parent_id')
            ->latest()
            ->with(['images', 'files', 'links', 'tags'])
            ->withCount(['subIssues', 'images', 'files'])
            ->paginate(10)
            ->withQueryString();

        $user = auth()->user();
        $userRole = $user->projectRoleOn($project->id);

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
            'projectTags' => IssueTag::query()
                ->where('project_id', $project->id)
                ->orderBy('name')
                ->get(['id', 'name', 'project_id']),
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
            'description' => $validated['description'] ?? null,
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
