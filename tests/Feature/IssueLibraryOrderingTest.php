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

class IssueLibraryOrderingTest extends TestCase
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

    public function test_issue_library_orders_top_level_issues_by_most_recently_edited(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);
        $project = $this->makeProject($user, $client, 'Library Project');

        Carbon::setTestNow('2026-08-01 10:00:00');
        $oldest = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Oldest issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);

        Carbon::setTestNow('2026-08-02 10:00:00');
        $middle = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Middle issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);

        Carbon::setTestNow('2026-08-03 10:00:00');
        $newest = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Newest issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);

        // Editing the oldest issue should bump it above the two newer issues.
        Carbon::setTestNow('2026-08-05 10:00:00');
        $oldest->update(['title' => 'Oldest issue (edited)']);

        Carbon::setTestNow();

        $this->actingAs($user)
            ->get(route('issues.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Issues/Index')
                ->where('issues.data.0.id', $oldest->id)
                ->where('issues.data.1.id', $newest->id)
                ->where('issues.data.2.id', $middle->id)
            );
    }

    public function test_newly_created_issue_appears_at_the_top_of_the_library(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);
        $project = $this->makeProject($user, $client, 'Library Project');

        Carbon::setTestNow('2026-08-01 10:00:00');
        $existing = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Existing issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);

        Carbon::setTestNow('2026-08-09 10:00:00');
        $fresh = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Freshly created issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);

        Carbon::setTestNow();

        $this->actingAs($user)
            ->get(route('issues.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Issues/Index')
                ->where('issues.data.0.id', $fresh->id)
                ->where('issues.data.1.id', $existing->id)
            );
    }
}
