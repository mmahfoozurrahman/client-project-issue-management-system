<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectProgressAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(User $user, string $name): Project
    {
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);

        return Project::withoutGlobalScope('user_owned')->create([
            'name' => $name,
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_project_progress_reflects_the_top_level_status_breakdown(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $project = $this->makeProject($user, 'Progress Project');

        $makeIssue = fn (string $status, ?int $parentId = null) => Issue::withoutGlobalScope('user_owned')->create([
            'title' => ucfirst($status).' issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => $status,
            'parent_id' => $parentId,
        ]);

        $makeIssue('todo');
        $makeIssue('todo');
        $makeIssue('inprogress');
        $parent = $makeIssue('done');

        // Sub-issues are excluded from the top-level progress analytics.
        $makeIssue('done', $parent->id);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('progress.total', 4)
                ->where('progress.todo', 2)
                ->where('progress.inprogress', 1)
                ->where('progress.done', 1)
                ->where('progress.percent', 25)
            );
    }

    public function test_project_progress_is_zero_for_a_project_without_issues(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $project = $this->makeProject($user, 'Empty Project');

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('progress.total', 0)
                ->where('progress.done', 0)
                ->where('progress.percent', 0)
            );
    }
}
