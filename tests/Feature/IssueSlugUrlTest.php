<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Issue;
use App\Models\IssueTag;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IssueSlugUrlTest extends TestCase
{
    use RefreshDatabase;

    private function makeIssue(User $user, string $title): Issue
    {
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);
        $project = Project::withoutGlobalScope('user_owned')->create([
            'name' => 'Slug Project',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        return Issue::withoutGlobalScope('user_owned')->create([
            'title' => $title,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);
    }

    public function test_issue_url_attribute_appends_a_title_slug(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $issue = $this->makeIssue($user, 'CSS - User interface outlook');

        $this->assertSame('css-user-interface-outlook', $issue->slug);
        $this->assertSame("/issues/{$issue->id}/css-user-interface-outlook", $issue->url);
    }

    public function test_issue_url_attribute_transliterates_bengali_titles(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $issue = $this->makeIssue($user, 'সূরা আল-কাফিরূন');

        $this->assertNotSame('', $issue->slug);
        $this->assertSame("/issues/{$issue->id}/{$issue->slug}", $issue->url);
    }

    public function test_issue_url_attribute_falls_back_to_id_when_title_has_no_slug(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $issue = $this->makeIssue($user, '!!! ??? 123');

        $this->assertSame('123', $issue->slug);
        $this->assertSame("/issues/{$issue->id}/123", $issue->url);
    }

    public function test_issue_slug_appends_attached_tag_names(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $issue = $this->makeIssue($user, 'Suura Al Kafiruun');

        $issue->tags()->attach([
            IssueTag::withoutGlobalScope('user_owned')->create([
                'name' => 'Dandapani',
                'slug' => 'dandapani',
                'project_id' => $issue->project_id,
                'user_id' => $user->id,
            ])->id,
            IssueTag::withoutGlobalScope('user_owned')->create([
                'name' => 'Focus And Awareness',
                'slug' => 'focus-and-awareness',
                'project_id' => $issue->project_id,
                'user_id' => $user->id,
            ])->id,
        ]);

        $withTags = Issue::withoutGlobalScope('user_owned')->with('tags')->findOrFail($issue->id);

        $this->assertSame(
            'suura-al-kafiruun-dandapani-focus-and-awareness',
            $withTags->slug
        );
        $this->assertSame(
            "/issues/{$issue->id}/suura-al-kafiruun-dandapani-focus-and-awareness",
            $withTags->url
        );

        // Without the relation loaded, the slug falls back to the title only (no N+1).
        $withoutTags = Issue::withoutGlobalScope('user_owned')->findOrFail($issue->id);
        $this->assertSame('suura-al-kafiruun', $withoutTags->slug);
    }

    public function test_issue_detail_page_is_reachable_with_and_without_the_slug(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $issue = $this->makeIssue($user, 'Slugged issue title');

        $this->actingAs($user)
            ->get("/issues/{$issue->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Issues/Show'));

        $this->actingAs($user)
            ->get("/issues/{$issue->id}/slugged-issue-title")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Issues/Show')
                ->where('issue.id', $issue->id)
                ->where('issue.url', "/issues/{$issue->id}/slugged-issue-title")
            );
    }

    public function test_deep_issue_routes_are_not_shadowed_by_the_optional_slug(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $issue = $this->makeIssue($user, 'Narration target');

        // /issues/{issue}/narration must still resolve to the narration route, not be
        // swallowed by the optional {slug} segment.
        $this->actingAs($user)
            ->get("/issues/{$issue->id}/narration")
            ->assertOk();
    }
}
