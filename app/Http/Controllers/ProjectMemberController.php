<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectMemberController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $validated = $request->validate([
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('is_admin', false),
                Rule::unique('project_members', 'user_id')->where('project_id', $project->id),
            ],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);

    // নতুন কোড: যদি ইউজার সুপার এডমিন না হন, তবে শুধু 'developer' রোল অ্যাসাইন হবে

        if (!auth()->user()->is_admin) {

            $validated['role_id'] = Role::where('slug', 'developer')->value('id');

        }

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id'    => $validated['user_id'],
            'role_id'    => $validated['role_id'],
        ]);

        return back()->with('success', 'Member added successfully.');
    }

    public function update(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        // নতুন কোড: শুধুমাত্র সুপার এডমিন রোল পরিবর্তন করতে পারবেন
        abort_unless(auth()->user()->is_admin, 403, 'Only Super Admin can change roles.');

        abort_unless($member->project_id === $project->id, 404);

        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);

        $member->update(['role_id' => $validated['role_id']]);

        return back()->with('success', 'Member role updated.');
    }

    public function destroy(Project $project, ProjectMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        abort_unless($member->project_id === $project->id, 404);
        abort_if($member->user_id === $project->user_id, 403, 'Cannot remove the project owner.');

        $member->delete();

        return back()->with('success', 'Member removed.');
    }

    public function searchUsers(Request $request, Project $project): \Illuminate\Http\JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $existingUserIds = ProjectMember::where('project_id', $project->id)->pluck('user_id');

        $users = User::where('is_admin', false)
            ->whereNotIn('id', $existingUserIds)
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->value();
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                );
            })
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }
}
