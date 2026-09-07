<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\Lesson;
use App\Models\Vault;
use App\Services\Lesson\LessonMarkdownExportService;
use App\Services\Lesson\LessonMarkdownImportService;
use App\Services\LessonNarrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LessonController extends Controller
{
    public function index(Request $request, Vault $vault, Folder $folder): Response
    {
        $search = $request->input('search');

        $lessons = Lesson::with('narration')->where('folder_id', $folder->id)
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($request->input('status') === 'published', fn ($q) => $q->where('is_published', true))
            ->when($request->input('status') === 'draft', fn ($q) => $q->where('is_published', false))
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($l) => [
                'id'           => $l->id,
                'title'        => $l->title,
                'content'      => $l->content,
                'sort_order'   => $l->sort_order,
                'is_free'      => $l->is_free,
                'is_published' => $l->is_published,
                'reading_time' => $l->reading_time,
                'difficulty'   => $l->difficulty,
                'created_at'   => $l->created_at->format('d M, Y'),
                'narration_status' => $l->narration?->status ?? 'idle',
                'narration_ready' => $l->narration?->status === 'ready' && ! empty($l->narration?->audio_paths),
                'narration_generated_at' => $l->narration?->generated_at?->format('d M, Y h:i A'),
            ]);

        $stats = [
            'total'     => Lesson::where('folder_id', $folder->id)->count(),
            'published' => Lesson::where('folder_id', $folder->id)->where('is_published', true)->count(),
            'draft'     => Lesson::where('folder_id', $folder->id)->where('is_published', false)->count(),
            'free'      => Lesson::where('folder_id', $folder->id)->where('is_free', true)->count(),
        ];

        return Inertia::render('Admin/Lessons', [
            'vault'   => ['id' => $vault->id, 'title' => $vault->title, 'slug' => $vault->slug],
            'folder'  => ['id' => $folder->id, 'title' => $folder->title],
            'lessons' => $lessons,
            'stats'   => $stats,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request, Vault $vault, Folder $folder): RedirectResponse
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'nullable|string',
            'sort_order'   => 'required|integer|min:1',
            'is_free'      => 'boolean',
            'is_published' => 'boolean',
            'reading_time' => 'nullable|integer|min:1',
            'difficulty'   => 'nullable|in:beginner,intermediate,advanced',
        ]);

        $data['difficulty'] = $data['difficulty'] ?? 'beginner';

        $folder->lessons()->create(array_merge($data, ['vault_id' => $vault->id]));

        return redirect()->route('admin.vaults.folders.lessons', [$vault, $folder])
            ->with('success', 'লেসন তৈরি হয়েছে।');
    }

    public function update(Request $request, Vault $vault, Folder $folder, Lesson $lesson): RedirectResponse
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'nullable|string',
            'sort_order'   => 'required|integer|min:1',
            'is_free'      => 'boolean',
            'is_published' => 'boolean',
            'reading_time' => 'nullable|integer|min:1',
            'difficulty'   => 'nullable|in:beginner,intermediate,advanced',
        ]);

        $data['difficulty'] = $data['difficulty'] ?? 'beginner';

        $lesson->update($data);

        return redirect()->route('admin.vaults.folders.lessons', [$vault, $folder])
            ->with('success', 'লেসন আপডেট হয়েছে।');
    }

    public function destroy(Vault $vault, Folder $folder, Lesson $lesson): RedirectResponse
    {
        $lesson->delete();

        return redirect()->route('admin.vaults.folders.lessons', [$vault, $folder])
            ->with('success', 'লেসন মুছে ফেলা হয়েছে।');
    }

    public function generateNarration(
        Request $request,
        Vault $vault,
        Folder $folder,
        Lesson $lesson,
        LessonNarrationService $narrationService
    ): RedirectResponse {
        abort_if($lesson->vault_id !== $vault->id || $lesson->folder_id !== $folder->id, 404);

        @set_time_limit(300);

        try {
            $record = $narrationService->generate($lesson);

            if ($record->status === 'ready') {
                return redirect()->route('admin.vaults.folders.lessons', [$vault, $folder])
                    ->with('success', "লেসন '{$lesson->title}'-এর অডিও সফলভাবে তৈরি হয়েছে।");
            }

            return redirect()->route('admin.vaults.folders.lessons', [$vault, $folder])
                ->with('error', $record->error_message ?: 'লেসনের অডিও তৈরিতে সমস্যা হয়েছে।');
        } catch (\RuntimeException $e) {
            // Rate limit or Gemini-specific errors — show user-friendly toast
            return redirect()->route('admin.vaults.folders.lessons', [$vault, $folder])
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('admin.vaults.folders.lessons', [$vault, $folder])
                ->with('error', 'অডিও তৈরির সময় সমস্যা হয়েছে: ' . $e->getMessage());
        }
    }

    public function import(
        Request $request,
        Vault $vault,
        Folder $folder,
        LessonMarkdownImportService $importer
    ): RedirectResponse {
        $request->validate([
            'files'   => 'required|array|min:1',
            'files.*' => 'required|file',
        ]);

        $result = $importer->import($request->file('files'), $vault, $folder);
        $message = count($result['created']) . ' imported; ' . count($result['skipped']) . ' skipped; ' . count($result['failed']) . ' failed.';

        return back()->with('success', $message)->with('lesson_import_result', $result);
    }

    public function exportMarkdown(
        Vault $vault,
        Folder $folder,
        LessonMarkdownExportService $exporter
    ): BinaryFileResponse {
        abort_if($folder->vault_id !== $vault->id, 404);

        $export = $exporter->export($vault);

        return response()->download($export['path'], $export['filename'])->deleteFileAfterSend(true);
    }

    public function listLessons(Request $request): Response
    {
        $search = $request->input('search');
        $vaultId = $request->input('vault_id');

        $lessons = Lesson::with(['vault:id,title', 'folder:id,title'])
            ->withCount('notes')
            ->withCount('interviewQuestions')
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($vaultId, fn ($q) => $q->where('vault_id', $vaultId))
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        $lessonsData = $lessons->getCollection()->map(fn ($l) => [
            'id'           => $l->id,
            'title'        => $l->title,
            'vault_title'  => $l->vault?->title ?? '—',
            'folder_title' => $l->folder?->title ?? '—',
            'notes_count'  => $l->notes_count,
            'interview_questions_count' => $l->interview_questions_count,
        ]);

        $lessons->setCollection($lessonsData);

        $vaults = Vault::orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/Lessons/Index', [
            'lessons' => $lessons,
            'vaults'  => $vaults,
            'filters' => $request->only(['search', 'vault_id']),
        ]);
    }

    public function lessonNotes(Lesson $lesson): Response
    {
        $lesson->load(['vault:id,title,slug', 'folder:id,title']);

        $notes = \App\Models\Note::where('lesson_id', $lesson->id)
            ->with(['user:id,name,email', 'images', 'referenceLinks'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($n) => [
                'id'            => $n->id,
                'user_name'     => $n->user?->name ?? '—',
                'user_email'    => $n->user?->email ?? '—',
                'content'       => $n->content,
                'updated_at'    => $n->updated_at->format('d M, Y h:i A'),
                'images'        => $n->images->map(fn ($img) => [
                    'id'            => $img->id,
                    'path'          => '/storage/' . $img->path,
                    'original_name' => $img->original_name,
                ]),
                'links'         => $n->referenceLinks->map(fn ($link) => [
                    'id'          => $link->id,
                    'url'         => $link->url,
                    'description' => $link->description ?? '',
                ]),
            ]);

        return Inertia::render('Admin/Lessons/ShowNotes', [
            'lesson' => [
                'id'         => $lesson->id,
                'title'      => $lesson->title,
                'vault_slug' => $lesson->vault?->slug,
            ],
            'notes'  => $notes,
        ]);
    }
}
