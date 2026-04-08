<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $projects = Project::query()
            ->with(['client:id,name'])
            ->withCount('issues')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
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

        return redirect()
            ->route('projects.index')
            ->with('success', "Project {$project->name} created successfully.");
    }

    public function show(Project $project): Response
    {
        $project->load([
            'client:id,name',
        ]);

        $issues = $project->issues()
            ->whereNull('parent_id')
            ->latest()
            ->with(['images'])
            ->withCount(['subIssues', 'images'])
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'issues' => $issues,
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Projects', 'href' => route('projects.index')],
                ['label' => $project->name],
            ],
        ]);
    }

    public function update(ProjectUpdateRequest $request, Project $project): RedirectResponse
    {
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
        $name = $project->name;
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', "Project {$name} deleted successfully.");
    }
}
