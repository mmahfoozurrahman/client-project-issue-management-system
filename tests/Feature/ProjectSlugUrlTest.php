<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectSlugUrlTest extends TestCase
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

    public function test_project_url_attribute_appends_a_name_slug(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $project = $this->makeProject($user, 'Social Media Content Planning & Posting');

        $this->assertSame('social-media-content-planning-posting', $project->slug);
        $this->assertSame(
            "/projects/{$project->id}/social-media-content-planning-posting",
            $project->url
        );
    }

    public function test_project_detail_page_is_reachable_with_and_without_the_slug(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $project = $this->makeProject($user, 'Alpha Project');

        $this->actingAs($user)
            ->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Projects/Show'));

        $this->actingAs($user)
            ->get("/projects/{$project->id}/alpha-project")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('project.id', $project->id)
                ->where('project.url', "/projects/{$project->id}/alpha-project")
            );
    }

    public function test_project_members_routes_are_not_shadowed_by_the_optional_slug(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $project = $this->makeProject($user, 'Members Project');

        $this->actingAs($user)
            ->get("/projects/{$project->id}/members/search?q=any")
            ->assertOk();
    }
}
