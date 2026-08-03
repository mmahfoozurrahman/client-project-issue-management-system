<?php

namespace App\Http\Controllers;

use App\Models\IssueTag;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $manageableProjectIds = $user->manageableProjectIds();

        abort_unless($user->canAccessTagsPage(), 403);

        $projects = Project::withoutGlobalScope('user_owned')
            ->whereIn('id', $manageableProjectIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $tags = IssueTag::query()
            ->whereIn('project_id', $manageableProjectIds)
            ->with(['project:id,name'])
            ->withCount('issues')
            ->orderBy('name')
            ->get(['id', 'project_id', 'name', 'slug', 'created_at']);

        return Inertia::render('Tags/Index', [
            'tags' => $tags,
            'projects' => $projects,
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Tags'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $manageableProjectIds = $user->manageableProjectIds();

        abort_unless($user->canAccessTagsPage(), 403);

        $validated = $request->validate([
            'project_id' => [
                'required',
                'integer',
                Rule::in($manageableProjectIds),
            ],
            'name' => ['required', 'string', 'max:50'],
        ]);

        $projectId = (int) $validated['project_id'];
        $name = trim($validated['name']);
        $slug = Str::slug($name);
        $slug = filled($slug) ? $slug : Str::lower(Str::replace(' ', '-', $name));

        IssueTag::query()->firstOrCreate(
            [
                'project_id' => $projectId,
                'slug' => $slug,
            ],
            [
                'name' => $name,
            ]
        );

        return back()->with('success', 'Tag saved successfully.');
    }

    public function update(Request $request, IssueTag $tag): RedirectResponse
    {
        $user = $request->user();
        $manageableProjectIds = $user->manageableProjectIds();

        abort_unless($user->canAccessTagsPage() && in_array($tag->project_id, $manageableProjectIds, true), 403);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('issue_tags', 'slug')
                    ->where(fn ($query) => $query->where('project_id', $tag->project_id))
                    ->ignore($tag->id),
            ],
        ]);

        $name = trim($validated['name']);
        $slug = Str::slug($name);
        $slug = filled($slug) ? $slug : Str::lower(Str::replace(' ', '-', $name));

        $tag->update([
            'name' => $name,
            'slug' => $slug,
        ]);

        return back()->with('success', 'Tag updated successfully.');
    }

    public function destroy(Request $request, IssueTag $tag): RedirectResponse
    {
        $user = $request->user();
        $manageableProjectIds = $user->manageableProjectIds();

        abort_unless($user->canAccessTagsPage() && in_array($tag->project_id, $manageableProjectIds, true), 403);

        $tag->delete();

        return back()->with('success', 'Tag deleted successfully.');
    }
}
