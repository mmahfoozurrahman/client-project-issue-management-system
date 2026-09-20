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

class IssueRelatedTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_related_issues_must_match_all_tags_of_current_issue_combined(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);
        $project = Project::withoutGlobalScope('user_owned')->create([
            'name' => 'Second Brain',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        $tagA = IssueTag::withoutGlobalScope('user_owned')->create([
            'name' => '46. Developer Mindsets',
            'slug' => '46-developer-mindsets',
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);
        $tagB = IssueTag::withoutGlobalScope('user_owned')->create([
            'name' => 'RabbilHasan',
            'slug' => 'rabbilhasan',
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);
        $tagC = IssueTag::withoutGlobalScope('user_owned')->create([
            'name' => 'Extra Tag',
            'slug' => 'extra-tag',
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);

        // Current issue with both Tag A and Tag B
        $currentIssue = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Current Parent Issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'done',
        ]);
        $currentIssue->tags()->attach([$tagA->id, $tagB->id]);

        // Issue with only Tag A (should NOT match)
        $issueOnlyA = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Only Mindset Issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'done',
        ]);
        $issueOnlyA->tags()->attach([$tagA->id]);

        // Issue with only Tag B (should NOT match)
        $issueOnlyB = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Only Rabbil Issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'done',
        ]);
        $issueOnlyB->tags()->attach([$tagB->id]);

        // Issue with both Tag A and Tag B (SHOULD match)
        $issueBoth = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Both Tags Issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'done',
        ]);
        $issueBoth->tags()->attach([$tagA->id, $tagB->id]);

        // Issue with Tag A, Tag B, and Tag C (SHOULD match)
        $issueAllThree = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'All Three Tags Issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'done',
        ]);
        $issueAllThree->tags()->attach([$tagA->id, $tagB->id, $tagC->id]);

        $this->actingAs($user)
            ->get(route('issues.show', $currentIssue))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Issues/Show')
                ->has('matchingIssues', 2)
                ->where('matchingIssues.0.id', $issueBoth->id)
                ->where('matchingIssues.0.matching_tag_count', 2)
                ->where('matchingIssues.1.id', $issueAllThree->id)
                ->where('matchingIssues.1.matching_tag_count', 2)
            );
    }

    public function test_related_issues_with_single_tag_matches_issues_having_that_tag(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);
        $project = Project::withoutGlobalScope('user_owned')->create([
            'name' => 'Second Brain',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        $tagA = IssueTag::withoutGlobalScope('user_owned')->create([
            'name' => 'Single Tag',
            'slug' => 'single-tag',
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);
        $tagB = IssueTag::withoutGlobalScope('user_owned')->create([
            'name' => 'Other Tag',
            'slug' => 'other-tag',
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);

        $currentIssue = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Current Single Tag Issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'done',
        ]);
        $currentIssue->tags()->attach([$tagA->id]);

        $matchingIssue = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Matching Issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'done',
        ]);
        $matchingIssue->tags()->attach([$tagA->id]);

        $nonMatchingIssue = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Non Matching Issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'done',
        ]);
        $nonMatchingIssue->tags()->attach([$tagB->id]);

        $this->actingAs($user)
            ->get(route('issues.show', $currentIssue))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Issues/Show')
                ->has('matchingIssues', 1)
                ->where('matchingIssues.0.id', $matchingIssue->id)
                ->where('matchingIssues.0.matching_tag_count', 1)
            );
    }

    public function test_related_issues_with_no_tags_returns_empty_collection(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);
        $project = Project::withoutGlobalScope('user_owned')->create([
            'name' => 'Second Brain',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        $currentIssue = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'No Tags Issue',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'done',
        ]);

        $this->actingAs($user)
            ->get(route('issues.show', $currentIssue))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Issues/Show')
                ->has('matchingIssues', 0)
            );
    }

    public function test_related_issues_and_nested_issues_include_quick_read_metadata(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'name' => 'John Dev']);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme Corp', 'user_id' => $user->id]);
        $project = Project::withoutGlobalScope('user_owned')->create([
            'name' => 'Client Project',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        $tag = IssueTag::withoutGlobalScope('user_owned')->create([
            'name' => 'UI Feature',
            'slug' => 'ui-feature',
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);

        $parentIssue = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Main Issue',
            'description' => '<p>Main description</p>',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'inprogress',
        ]);
        $parentIssue->tags()->attach([$tag->id]);

        $childIssue = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Child Task',
            'description' => '<p>Child issue detail</p>',
            'project_id' => $project->id,
            'parent_id' => $parentIssue->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);

        $relatedIssue = Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Related Issue With Same Tag',
            'description' => '<p>Detailed description for quick read</p>',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'done',
        ]);
        $relatedIssue->tags()->attach([$tag->id]);

        $this->actingAs($user)
            ->get(route('issues.show', $parentIssue))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Issues/Show')
                ->has('matchingIssues', 1)
                ->where('matchingIssues.0.id', $relatedIssue->id)
                ->where('matchingIssues.0.description', '<p>Detailed description for quick read</p>')
                ->has('matchingIssues.0.user')
                ->has('matchingIssues.0.images')
                ->has('matchingIssues.0.files')
                ->has('matchingIssues.0.links')
                ->has('matchingIssues.0.tags')
                ->has('issue.sub_issues', 1)
                ->where('issue.sub_issues.0.id', $childIssue->id)
                ->where('issue.sub_issues.0.description', '<p>Child issue detail</p>')
                ->has('issue.sub_issues.0.user')
                ->has('issue.sub_issues.0.parent_issue')
            );
    }
}
