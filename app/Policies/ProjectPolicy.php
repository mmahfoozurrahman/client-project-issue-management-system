<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->is_admin) {
            return true;
        }

        return null;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->canOnProject('project.view', $project->id);
    }

    public function update(User $user, Project $project): bool
    {
        //return $project->user_id === $user->id;
        return $user->canOnProject('project.edit', $project->id);
    }

    public function delete(User $user, Project $project): bool
    {
        //return $project->user_id === $user->id;
        return $user->canOnProject('project.delete', $project->id);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->canOnProject('project.manage_members', $project->id);
    }
}
