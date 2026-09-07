<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Vault;
use App\Services\LessonNarrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonNarrationController extends Controller
{
    public function show(Vault $vault, Lesson $lesson, LessonNarrationService $service): JsonResponse
    {
        $this->abortIfLessonMismatch($vault, $lesson);
        $this->abortIfUnauthorised();

        return response()->json($service->currentState($lesson));
    }

    public function audio(Vault $vault, Lesson $lesson, int $track): StreamedResponse
    {
        $this->abortIfLessonMismatch($vault, $lesson);
        $this->abortIfUnauthorised();

        $record = $lesson->narration;
        $path = $record?->audio_paths[$track] ?? null;
        $disk = config('lesson_narration.storage_disk', 'private');

        abort_if($record?->status !== 'ready' || ! is_string($path), 404);
        abort_if(! Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, basename($path), [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private function abortIfLessonMismatch(Vault $vault, Lesson $lesson): void
    {
        abort_if(! $vault->is_published, 404);
        abort_if(! $lesson->is_published || $lesson->vault_id !== $vault->id, 404);
    }

    private function abortIfUnauthorised(): void
    {
        $user = Auth::user();

        abort_if(! $user, 401);
        abort_if(! $user->isAdmin() && ! $user->canUseLessonNarration(), 403);
    }
}
