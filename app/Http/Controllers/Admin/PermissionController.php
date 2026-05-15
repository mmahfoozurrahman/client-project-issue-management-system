<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => Permission::withCount('roles')->orderBy('name')->get(),
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Admin'],
                ['label' => 'Permissions'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\.\_\-]+$/', Rule::unique('permissions', 'slug')],
        ]);

        Permission::create($validated);

        return back()->with('success', 'Permission created.');
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\.\_\-]+$/', Rule::unique('permissions', 'slug')->ignore($permission->id)],
        ]);

        $permission->update($validated);

        return back()->with('success', 'Permission updated.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        return back()->with('success', 'Permission deleted.');
    }
}
