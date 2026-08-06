<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIntegrationIssueRequest;
use App\Models\Issue;
use App\Models\IssueAiSource;
use App\Models\Project;
use App\Services\IssueService;
use App\Services\RichTextSanitizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class IntegrationIssueController extends Controller
{
    public function __construct(
        private readonly IssueService $issueService,
        private readonly RichTextSanitizer $richTextSanitizer,
    ) {
    }

    public function store(StoreIntegrationIssueRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $existing = $this->existingSource($validated['source_tool'], $validated['external_source_id']);

        if ($existing) {
            abort_unless($request->user()->can('view', $existing->issue), 403);

            return $this->issueResponse($existing->issue, false);
        }

        $project = Project::withoutGlobalScope('user_owned')->findOrFail($validated['project_id']);
        abort_unless(
            $request->user()->can('create', [Issue::class, $project]),
            403,
            'You may not create issues for this project.'
        );

        try {
            $issue = DB::transaction(function () use ($validated, $project, $request): Issue {
                $parentIssue = $this->issueService->resolveParentIssue(
                    $validated['parent_id'] ?? null,
                    $project->id,
                );

                $issue = Issue::query()->create([
                    'title' => $validated['title'],
                    'description' => $this->richTextSanitizer->sanitize($validated['description'] ?? null),
                    'status' => $validated['status'],
                    'done_at' => $validated['status'] === 'done' ? now() : null,
                    'project_id' => $project->id,
                    'parent_id' => $parentIssue?->id,
                    'user_id' => $request->user()->id,
                ]);

                $this->issueService->syncTags($validated['tag_names'] ?? null, $issue, $project->id);
                $this->issueService->syncLinks($this->linksForIssue($validated), $issue);

                $issue->aiSource()->create([
                    'source_tool' => $validated['source_tool'],
                    'model' => $validated['model'] ?? null,
                    'source_url' => $validated['source_url'] ?? null,
                    'external_source_id' => $validated['external_source_id'],
                    'repository' => $validated['repository'] ?? null,
                    'git_branch' => $validated['git_branch'] ?? null,
                    'commit_hash' => $validated['commit_hash'] ?? null,
                ]);

                return $issue;
            });
        } catch (QueryException $exception) {
            $existing = $this->existingSource($validated['source_tool'], $validated['external_source_id']);

            if (! $existing) {
                throw $exception;
            }

            abort_unless($request->user()->can('view', $existing->issue), 403);

            return $this->issueResponse($existing->issue, false);
        }

        return $this->issueResponse($issue, true);
    }

    private function existingSource(string $sourceTool, string $externalSourceId): ?IssueAiSource
    {
        return IssueAiSource::query()
            ->with('issue')
            ->where('source_tool', $sourceTool)
            ->where('external_source_id', $externalSourceId)
            ->first();
    }

    /** @param array<string, mixed> $validated */
    private function linksForIssue(array $validated): array
    {
        $links = $validated['links'] ?? [];

        if (filled($validated['source_url'] ?? null)) {
            $links[] = [
                'url' => $validated['source_url'],
                'label' => ucfirst($validated['source_tool']).' source',
            ];
        }

        return collect($links)
            ->filter(fn ($link) => is_array($link) && filled($link['url'] ?? null))
            ->unique(fn (array $link) => $link['url'])
            ->values()
            ->all();
    }

    private function issueResponse(Issue $issue, bool $created): JsonResponse
    {
        return response()->json([
            'issue_id' => $issue->id,
            'url' => route('issues.show', $issue),
            'created' => $created,
        ], $created ? 201 : 200);
    }
}
