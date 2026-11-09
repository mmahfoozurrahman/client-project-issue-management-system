<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueAutosaveTest extends TestCase
{
    use RefreshDatabase;

    private function makeIssue(User $user): Issue
    {
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);
        $project = Project::withoutGlobalScope('user_owned')->create([
            'name' => 'Autosave Project',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        return Issue::withoutGlobalScope('user_owned')->create([
            'title' => 'Before',
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'todo',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Issue $issue, array $overrides = []): array
    {
        return array_merge([
            'title' => 'After',
            'description' => '<p>Updated</p>',
            'status' => 'inprogress',
            'project_id' => $issue->project_id,
            'parent_id' => '',
            'links' => [],
            'tag_names' => [],
        ], $overrides);
    }

    public function test_background_autosave_persists_silently_without_a_success_flash(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $issue = $this->makeIssue($user);

        $this->actingAs($user)
            ->put("/issues/{$issue->id}", $this->payload($issue, ['autosave' => true]))
            ->assertRedirect()
            ->assertSessionMissing('success');

        $this->assertSame('After', $issue->fresh()->title);
    }

    public function test_manual_save_still_flashes_the_success_message(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $issue = $this->makeIssue($user);

        $this->actingAs($user)
            ->put("/issues/{$issue->id}", $this->payload($issue))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('After', $issue->fresh()->title);
    }
}
