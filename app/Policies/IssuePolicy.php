<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

class IssuePolicy
{
    public function before(User $user): ?bool
    {
        if ($user->is_admin) {
            return true;
        }

        return null;
    }

    public function view(User $user, Issue $issue): bool
    {
        return $user->canOnProject('issue.view', $issue->project_id);
    }

    /**
     * Called as: $this->authorize('create', [Issue::class, $project])
     */
    public function create(User $user, Project $project): bool
    {
        return $user->canOnProject('issue.create', $project->id);
    }

    public function update(User $user, Issue $issue): bool
    {
        return $user->canOnProject('issue.edit', $issue->project_id);
    }

    public function delete(User $user, Issue $issue): bool
    {
        return $user->canOnProject('issue.delete', $issue->project_id);
    }

    public function changeStatus(User $user, Issue $issue): bool
    {
        return $user->canOnProject('issue.change_status', $issue->project_id);
    }

    public function uploadAttachment(User $user, Issue $issue): bool
    {
        return $user->canOnProject('issue.upload_attachment', $issue->project_id);
    }

    public function deleteAttachment(User $user, Issue $issue): bool
    {
        return $user->canOnProject('issue.delete_attachment', $issue->project_id);
    }
}
