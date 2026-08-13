<?php

namespace App\Http\Controllers;

use App\Models\IssueTag;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        $request->validate([
            'project_id' => ['nullable', 'integer', Rule::in($manageableProjectIds)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $projectId = $request->filled('project_id') ? (int) $request->input('project_id') : null;
        $queryText = trim((string) $request->input('q', ''));

        $projects = Project::withoutGlobalScope('user_owned')
            ->whereIn('id', $manageableProjectIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $tags = IssueTag::query()
            ->whereIn('project_id', $manageableProjectIds)
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->when($queryText !== '', function ($query) use ($queryText) {
                $query->where(function ($innerQuery) use ($queryText) {
                    $innerQuery
                        ->where('name', 'like', "%{$queryText}%")
                        ->orWhere('slug', 'like', "%{$queryText}%");
                });
            })
            ->with(['project:id,name'])
            ->withCount('issues')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $tagSuggestions = IssueTag::query()
            ->whereIn('project_id', $manageableProjectIds)
            ->with('project:id,name')
            ->orderBy('name')
            ->get(['id', 'project_id', 'name', 'slug']);

        return Inertia::render('Tags/Index', [
            'tags' => $tags,
            'tagSuggestions' => $tagSuggestions,
            'projects' => $projects,
            'filters' => [
                'project_id' => $request->input('project_id'),
                'q' => $request->input('q'),
            ],
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

        $exists = IssueTag::query()
            ->where('project_id', $projectId)
            ->where('slug', $slug)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This tag already exists for the selected project.',
            ]);
        }

        IssueTag::query()->create([
            'project_id' => $projectId,
            'name' => $name,
            'slug' => $slug,
        ]);

        return back()->with('success', 'Tag saved successfully.');
    }

    public function update(Request $request, IssueTag $tag): RedirectResponse
    {
        $user = $request->user();
        $manageableProjectIds = $user->manageableProjectIds();

        abort_unless($user->canAccessTagsPage() && in_array($tag->project_id, $manageableProjectIds, true), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $name = trim($validated['name']);
        $slug = Str::slug($name);
        $slug = filled($slug) ? $slug : Str::lower(Str::replace(' ', '-', $name));

        $exists = IssueTag::query()
            ->where('project_id', $tag->project_id)
            ->where('slug', $slug)
            ->whereKeyNot($tag->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This tag already exists for this project.',
            ]);
        }

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
