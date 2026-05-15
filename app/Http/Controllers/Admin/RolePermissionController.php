<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permission_ids'   => ['present', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $role->permissions()->sync($validated['permission_ids']);

        return back()->with('success', "Permissions updated for role {$role->name}.");
    }
}
