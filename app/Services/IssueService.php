<?php

namespace App\Services;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

class IssueService
{
    public function detailRelations(): array
    {
        return [
            'project:id,name,client_id',
            'project.client:id,name',
            'images',
            'files',
            'links',
            'parentIssue:id,title,project_id,parent_id',
            'subIssues' => fn ($query) => $query
                ->with(['images', 'files', 'links', 'project:id,name'])
                ->withCount(['subIssues', 'images', 'files'])
                ->latest(),
        ];
    }

    public function resolveParentIssue(?int $parentId, int $projectId, ?int $ignoreIssueId = null): ?Issue
    {
        if (! $parentId) {
            return null;
        }

        $query = Issue::query()
            ->where('project_id', $projectId)
            ->whereKey($parentId);

        if ($ignoreIssueId) {
            $query->whereKeyNot($ignoreIssueId);
        }

        return $query->firstOrFail();
    }

    public function storeAttachments(Request $request, Issue $issue): void
    {
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $this->storeWithTimestampPrefix($file, 'issues/images');

                $issue->images()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $this->storeWithTimestampPrefix($file, 'issues/files');

                $issue->files()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }
    }

    public function syncLinks(?array $links, Issue $issue): void
    {
        if (! is_array($links)) {
            return;
        }

        $payload = collect($links)
            ->filter(fn ($link) => is_array($link) && filled($link['url'] ?? null))
            ->map(function ($link) {
                $url = trim((string) $link['url']);

                return [
                    'url' => $url,
                    'label' => filled($link['label'] ?? null) ? trim((string) $link['label']) : null,
                    'is_external' => $this->isExternalLink($url),
                ];
            })
            ->values();

        $issue->links()->delete();

        if ($payload->isEmpty()) {
            return;
        }

        $issue->links()->createMany($payload->all());
    }

    public function loadNestedSubIssues(Issue $issue): void
    {
        $issue->load([
            'subIssues' => fn ($query) => $query
                ->with(['images', 'files', 'links', 'project:id,name', 'parentIssue:id,title,project_id,parent_id'])
                ->withCount(['subIssues', 'images', 'files'])
                ->latest(),
        ]);

        $issue->subIssues->each(fn (Issue $subIssue) => $this->loadNestedSubIssues($subIssue));
    }

    public function parentIssueOptions(Issue $issue): Collection
    {
        $excludedIds = [$issue->id, ...$this->descendantIssueIds($issue)];

        return $this->issueOptionsForProject($issue->project_id, $excludedIds);
    }

    public function issueOptionsForProject(int $projectId, array $excludedIds = []): Collection
    {
        $issues = Issue::query()
            ->where('project_id', $projectId)
            ->orderBy('title')
            ->get(['id', 'title', 'parent_id']);

        return $this->flattenIssueOptions($issues, null, 0, $excludedIds)->values();
    }

    private function descendantIssueIds(Issue $issue): array
    {
        $descendantIds = [];
        $pendingIds = [$issue->id];

        while ($pendingIds !== []) {
            $childIds = Issue::query()
                ->whereIn('parent_id', $pendingIds)
                ->pluck('id')
                ->all();

            if ($childIds === []) {
                break;
            }

            $descendantIds = [...$descendantIds, ...$childIds];
            $pendingIds = $childIds;
        }

        return array_values(array_unique($descendantIds));
    }

    private function flattenIssueOptions($issues, ?int $parentId = null, int $depth = 0, array $excludedIds = []): Collection
    {
        return $issues
            ->where('parent_id', $parentId)
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->flatMap(function (Issue $entry) use ($issues, $depth, $excludedIds) {
                $children = $this->flattenIssueOptions($issues, $entry->id, $depth + 1, $excludedIds);

                if (in_array($entry->id, $excludedIds, true)) {
                    return $children;
                }

                return collect([
                    [
                        'id' => $entry->id,
                        'title' => $entry->title,
                        'depth' => $depth,
                        'label' => str_repeat('- ', $depth).$entry->title,
                    ],
                    ...$children->all(),
                ]);
            });
    }

    private function isExternalLink(string $url): bool
    {
        $normalized = Str::lower($url);

        return Str::startsWith($normalized, ['http://', 'https://']);
    }

    private function storeWithTimestampPrefix($file, string $directory): string
    {
        $original = $this->sanitizeFilename($file->getClientOriginalName());
        $timestamp = now()->format('Ymd-His');
        $baseName = "{$timestamp}-{$original}";
        $candidate = $baseName;
        $counter = 1;

        while (Storage::disk('public')->exists("{$directory}/{$candidate}")) {
            $counter++;
            $candidate = "{$timestamp}-{$counter}-{$original}";
        }

        return $file->storeAs($directory, $candidate, 'public');
    }

    private function sanitizeFilename(string $name): string
    {
        return Str::of($name)
            ->replace(['\\', '/'], '-')
            ->toString();
    }
}
