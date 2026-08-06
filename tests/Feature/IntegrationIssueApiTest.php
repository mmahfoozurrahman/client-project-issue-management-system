<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Issue;
use App\Models\IssueIntegrationToken;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationIssueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_issue_with_ai_metadata_tags_and_source_link(): void
    {
        [$user, $project, $token] = $this->integrationContext();

        $response = $this->withToken($token)->postJson('/api/integrations/issues', [
            'project_id' => $project->id,
            'title' => 'Add AI-assisted issue saving',
            'description' => '<h2>Original Problem</h2><p>Manual issue capture.</p><script>alert(1)</script>',
            'tag_names' => ['AI', 'integration'],
            'status' => 'done',
            'source_tool' => 'chatgpt',
            'model' => 'gpt-5.6',
            'source_url' => 'https://chatgpt.com/share/example',
            'external_source_id' => 'chatgpt-session-123',
            'repository' => 'issue-listing-2',
            'git_branch' => 'feature/ai-issues',
            'commit_hash' => 'abc123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('issue_id', 1)
            ->assertJsonPath('url', route('issues.show', 1));

        $issue = Issue::withoutGlobalScope('user_owned')->with(['tags', 'links', 'aiSource'])->findOrFail(1);
        $this->assertSame($user->id, $issue->user_id);
        $this->assertSame('done', $issue->status);
        $this->assertNotNull($issue->done_at);
        $this->assertStringContainsString('<h2>Original Problem</h2>', $issue->description);
        $this->assertStringNotContainsString('script', $issue->description);
        $this->assertSame(['AI', 'integration'], $issue->tags->pluck('name')->all());
        $this->assertSame('https://chatgpt.com/share/example', $issue->links->sole()->url);
        $this->assertSame('gpt-5.6', $issue->aiSource->model);
        $this->assertSame('chatgpt-session-123', $issue->aiSource->external_source_id);
    }

    public function test_it_returns_the_original_issue_for_a_duplicate_external_source(): void
    {
        [, $project, $token] = $this->integrationContext();
        $payload = [
            'project_id' => $project->id,
            'title' => 'Original title',
            'status' => 'todo',
            'source_tool' => 'codex',
            'external_source_id' => 'codex-task-456',
        ];

        $this->withToken($token)->postJson('/api/integrations/issues', $payload)->assertCreated();
        $duplicate = $this->withToken($token)->postJson('/api/integrations/issues', [...$payload, 'title' => 'Changed title']);

        $duplicate->assertOk()
            ->assertJsonPath('issue_id', 1)
            ->assertJsonPath('created', false);
        $this->assertSame(1, Issue::withoutGlobalScope('user_owned')->count());
        $this->assertSame('Original title', Issue::withoutGlobalScope('user_owned')->findOrFail(1)->title);
    }

    public function test_it_requires_a_valid_bearer_token_and_valid_status(): void
    {
        [, $project] = $this->integrationContext();
        $payload = [
            'project_id' => $project->id,
            'title' => 'Bad request',
            'status' => 'closed',
            'source_tool' => 'codex',
            'external_source_id' => 'codex-task-789',
        ];

        $this->postJson('/api/integrations/issues', $payload)->assertUnauthorized();
        $this->withToken('not-a-real-token')->postJson('/api/integrations/issues', $payload)->assertUnauthorized();

        [, , $token] = $this->integrationContext();
        $this->withToken($token)->postJson('/api/integrations/issues', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    /** @return array{0: User, 1: Project, 2: string} */
    private function integrationContext(): array
    {
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::withoutGlobalScope('user_owned')->create(['name' => 'Acme', 'user_id' => $user->id]);
        $project = Project::withoutGlobalScope('user_owned')->create([
            'name' => 'Issue tracker',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);
        [, $token] = IssueIntegrationToken::issue($user, 'Test integration');

        return [$user, $project, $token];
    }
}
