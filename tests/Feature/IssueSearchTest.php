<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Issue;
use App\Models\IssueLink;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IssueSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_search_matches_link_urls_and_exposes_link_aware_suggestions(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);
        $project = Project::withoutGlobalScope('user_owned')->create([
            'name' => 'Video research',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        $linkedIssue = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Review the tutorial',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);
        IssueLink::create([
            'issue_id' => $linkedIssue->id,
            'url' => 'https://www.youtube.com/watch?v=aki1A7mYWYE',
            'label' => 'YouTube tutorial',
        ]);

        Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Unrelated issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);

        $this->actingAs($user)
            ->get('/issues?q=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3Daki1A7mYWYE')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Issues/Index')
                ->has('issues.data', 1)
                ->where('issues.data.0.id', $linkedIssue->id)
                ->has('issueSearchSuggestions', 2)
                ->where('issueSearchSuggestions.0.links.0.url', 'https://www.youtube.com/watch?v=aki1A7mYWYE'));
    }

    public function test_search_suggestions_requires_at_least_two_characters(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->getJson('/issues/search-suggestions?q=a')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_search_suggestions_matches_description_and_returns_snippet(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);
        $project = Project::withoutGlobalScope('user_owned')->create([
            'name' => 'Backend infra',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        $issue = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Server setup',
            'description' => '<p>Please configure the <strong>redis</strong> queue worker for reliable notifications.</p>',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/issues/search-suggestions?q=redis')
            ->assertOk()
            ->assertJsonCount(1);

        $data = $response->json(0);
        $this->assertEquals($issue->id, $data['id']);
        $this->assertEquals('description', $data['match_type']);
        $this->assertStringContainsString('redis', strtolower($data['snippet']));
    }

    public function test_search_suggestions_filters_by_project_id(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);
        $projectA = Project::withoutGlobalScope('user_owned')->create([
            'name' => 'Project A',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);
        $projectB = Project::withoutGlobalScope('user_owned')->create([
            'name' => 'Project B',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Database indexing',
            'project_id' => $projectA->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);
        Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Database migration',
            'project_id' => $projectB->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/issues/search-suggestions?q=Database&project_id={$projectA->id}")
            ->assertOk()
            ->assertJsonCount(1);

        $this->assertEquals($projectA->id, $response->json('0.project_id'));
    }
}
