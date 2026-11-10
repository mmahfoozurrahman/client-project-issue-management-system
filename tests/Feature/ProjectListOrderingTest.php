<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectListOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(User $user, Client $client, string $name): Project
    {
        return Project::withoutGlobalScope('user_owned')->create([
            'name' => $name,
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);
    }

    private function makeIssue(User $user, Project $project, string $title): Issue
    {
        return Issue::withoutGlobalScope('user_owned')->create([
            'title' => $title,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);
    }

    public function test_projects_are_ordered_by_their_most_recent_issue_activity(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);

        Carbon::setTestNow('2026-08-01 10:00:00');
        $oldestProject = $this->makeProject($user, $client, 'Oldest Project');

        Carbon::setTestNow('2026-08-02 10:00:00');
        $middleProject = $this->makeProject($user, $client, 'Middle Project');

        Carbon::setTestNow('2026-08-03 10:00:00');
        $newestProject = $this->makeProject($user, $client, 'Newest Project');

        // A fresh issue in the oldest project lifts it above the two projects that
        // were themselves created more recently but have no issue activity yet.
        Carbon::setTestNow('2026-08-10 10:00:00');
        $this->makeIssue($user, $oldestProject, 'Fresh activity');

        Carbon::setTestNow();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('projects.data.0.id', $oldestProject->id)
                ->where('projects.data.1.id', $newestProject->id)
                ->where('projects.data.2.id', $middleProject->id)
            );
    }

    public function test_editing_an_issue_lifts_its_project_to_the_top(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);

        Carbon::setTestNow('2026-08-01 10:00:00');
        $projectA = $this->makeProject($user, $client, 'Project A');
        $issueA = $this->makeIssue($user, $projectA, 'Issue in A');

        Carbon::setTestNow('2026-08-02 10:00:00');
        $projectB = $this->makeProject($user, $client, 'Project B');
        $this->makeIssue($user, $projectB, 'Issue in B');

        // Project B has the freshest activity so far, so it should lead the list.
        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('projects.data.0.id', $projectB->id)
                ->where('projects.data.1.id', $projectA->id)
            );

        // Editing the older issue should now push Project A to the top instead.
        Carbon::setTestNow('2026-08-05 10:00:00');
        $issueA->update(['title' => 'Issue in A (edited)']);
        Carbon::setTestNow();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('projects.data.0.id', $projectA->id)
                ->where('projects.data.1.id', $projectB->id)
            );
    }

    public function test_projects_without_issues_fall_back_to_creation_date_order(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);

        Carbon::setTestNow('2026-08-01 10:00:00');
        $first = $this->makeProject($user, $client, 'First Project');

        Carbon::setTestNow('2026-08-04 10:00:00');
        $second = $this->makeProject($user, $client, 'Second Project');

        Carbon::setTestNow();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('projects.data.0.id', $second->id)
                ->where('projects.data.1.id', $first->id)
            );
    }
}
