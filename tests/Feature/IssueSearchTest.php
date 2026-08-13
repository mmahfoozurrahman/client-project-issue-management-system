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
}
