<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Roles/Index', [
            'roles'       => Role::with('permissions:id,name,slug')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(['id', 'name', 'slug']),
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Admin'],
                ['label' => 'Roles'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('roles', 'slug')],
        ]);

        Role::create($validated);

        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('roles', 'slug')->ignore($role->id)],
        ]);

        $role->update($validated);

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if(
            $role->projectMembers()->exists(),
            409,
            'Cannot delete a role that is assigned to project members.'
        );

        $role->delete();

        return back()->with('success', 'Role deleted.');
    }
}
