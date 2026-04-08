<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserStoreRequest;
use App\Http\Requests\AdminUserUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->latest()
            ->paginate(10, ['id', 'name', 'email', 'is_admin', 'created_at'])
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'User Management'],
            ],
        ]);
    }

    public function store(AdminUserStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} created successfully.");
    }

    public function update(AdminUserUpdateRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} updated successfully.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()?->is($user), 422, 'You cannot delete your own account.');

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$name} deleted successfully.");
    }
}
