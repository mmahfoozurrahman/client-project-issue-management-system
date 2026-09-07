<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateIssueNarration;
use App\Models\Issue;
use App\Models\IssueNarration;
use App\Services\IssueNarrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IssueNarrationController extends Controller
{
    public function show(Issue $issue, IssueNarrationService $service): JsonResponse
    {
        $this->authorize('view', $issue);

        return response()->json($service->currentState($issue));
    }

    public function generate(Request $request, Issue $issue, IssueNarrationService $service): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $force = (bool) $request->boolean('force');
        $service->generate($issue, $force);

        return response()->json($service->currentState($issue));
    }

    public function audio(Issue $issue, int $track): StreamedResponse
    {
        $this->authorize('view', $issue);

        $record = $issue->narration;
        $path = $record?->audio_paths[$track] ?? null;
        $disk = config('issue_narration.storage_disk', 'local');

        abort_if($record?->status !== 'ready' || ! is_string($path), 404);
        abort_if(! Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, basename($path), [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
