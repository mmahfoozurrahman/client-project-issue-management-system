<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\Lesson;
use App\Models\LessonNarration;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LessonNarrationService
{
    public function currentState(Lesson $lesson): array
    {
        $lesson->loadMissing('narration');
        $record = $lesson->narration;
        $sourceHash = $this->sourceHash($lesson);
        $hashMatches = $record && hash_equals($record->source_hash ?? '', $sourceHash);
        $status = $record?->status ?? 'idle';

        if ($record && ! $hashMatches) {
            $status = 'stale';
        }

        $audioUrls = $record && $hashMatches && $record->status === 'ready'
            ? $this->audioUrls($record->audio_paths ?? [], $lesson)
            : [];
        $missingAudio = $record && $hashMatches && $record->status === 'ready' && empty($audioUrls);
        $isAvailable = $record && $hashMatches && $record->status === 'ready' && ! $missingAudio;

        return [
            'can_use' => true,
            'provider' => $record?->provider ?? 'gemini',
            'locale' => $record?->locale ?? config('lesson_narration.locale', 'bn-IN'),
            'voice_name' => $record?->voice_name ?? $this->getGeminiVoiceName(),
            'voice_label' => $this->formatVoiceLabel($record?->voice_name ?? $this->getGeminiVoiceName()),
            'status' => $status,
            'source_hash' => $sourceHash,
            'record_source_hash' => $record?->source_hash,
            'is_available' => $isAvailable,
            'needs_refresh' => ! $record || ! $hashMatches || in_array($status, ['failed', 'stale'], true) || $missingAudio,
            'audio_urls' => $audioUrls,
            'track_count' => count($audioUrls),
            'requested_at' => $record?->requested_at?->toIso8601String(),
            'generated_at' => $record?->generated_at?->toIso8601String(),
            'error_message' => $record?->error_message,
        ];
    }

    public function queueGeneration(Lesson $lesson, ?int $requestedByUserId = null, bool $force = false): LessonNarration
    {
        $sourceHash = $this->sourceHash($lesson);
        $record = LessonNarration::firstOrNew(['lesson_id' => $lesson->id]);

        if (
            ! $force
            && $record->exists
            && $record->status === 'ready'
            && hash_equals($record->source_hash ?? '', $sourceHash)
            && ! empty($record->audio_paths)
        ) {
            return $record;
        }

        if ($force && $record->exists) {
            $this->deleteNarrationFiles($record->audio_paths ?? []);
        }

        $record->fill([
            'provider' => 'gemini',
            'locale' => config('lesson_narration.locale', 'bn-IN'),
            'source_hash' => $sourceHash,
            'status' => 'queued',
            'audio_paths' => null,
            'error_message' => null,
            'requested_at' => now(),
            'generated_at' => null,
        ]);

        $record->save();

        \App\Jobs\GenerateLessonNarration::dispatch($lesson->id, $requestedByUserId);

        return $record;
    }

    public function generateByLessonId(int $lessonId): LessonNarration
    {
        $lesson = Lesson::query()->with('narration')->findOrFail($lessonId);

        return $this->generate($lesson);
    }

    public function generate(Lesson $lesson): LessonNarration
    {
        $record = LessonNarration::firstOrNew(['lesson_id' => $lesson->id]);
        $segments = $this->buildNarrationSegments($lesson);
        $sourceHash = $this->sourceHash($lesson);
        $provider = 'gemini';
        $locale = config('lesson_narration.locale', 'bn-IN');

        if (empty($segments)) {
            $record->fill([
                'provider' => $provider,
                'locale' => $locale,
                'source_hash' => $sourceHash,
                'status' => 'failed',
                'error_message' => 'No lesson text was available for narration.',
                'requested_at' => $record->requested_at ?? now(),
            ])->save();

            return $record;
        }

        $apiKey = $this->getGeminiApiKey();

        if ($apiKey === '') {
            $record->fill([
                'provider' => $provider,
                'locale' => $locale,
                'source_hash' => $sourceHash,
                'status' => 'failed',
                'error_message' => 'Gemini API key is missing. Set GEMINI_API_KEY in .env or Admin Settings.',
                'requested_at' => $record->requested_at ?? now(),
            ])->save();

            return $record;
        }

        // Check configured rate limits before processing
        $this->checkRateLimits($lesson);

        $voiceName = $this->getGeminiVoiceName();
        $model = $this->getGeminiModel();
        $chunkDelay = $this->getGeminiChunkDelay();

        $record->fill([
            'provider' => $provider,
            'locale' => $locale,
            'voice_name' => $voiceName,
            'source_hash' => $sourceHash,
            'status' => 'processing',
            'audio_paths' => null,
            'error_message' => null,
            'requested_at' => $record->requested_at ?? now(),
        ])->save();

        $chunks = $this->chunkSegments($segments, $this->getGeminiChunkChars());
        $disk = config('lesson_narration.storage_disk', 'private');
        $paths = [];
        $startTime = microtime(true);
        $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

        try {
            foreach ($chunks as $index => $chunk) {
                // Inter-chunk throttle delay to prevent bursting Google AI Studio RPM limits
                if ($index > 0 && $chunkDelay > 0) {
                    sleep($chunkDelay);
                }

                [$pcm, $chunkUsage] = $this->synthesizeGeminiChunk($chunk, $apiKey, $model, $voiceName, $lesson);
                $wavData = $this->pcmToWav($pcm);
                $path = $this->audioPath($lesson, $sourceHash, $index);

                Storage::disk($disk)->put($path, $wavData);
                $paths[] = $path;

                $totalUsage['prompt_tokens'] += $chunkUsage['prompt_tokens'];
                $totalUsage['completion_tokens'] += $chunkUsage['audio_tokens'];
                $totalUsage['total_tokens'] += $chunkUsage['total_tokens'];
            }

            $record->fill([
                'status' => 'ready',
                'audio_paths' => $paths,
                'generated_at' => now(),
                'error_message' => null,
            ])->save();

            // Log successful AI usage in database
            $this->logUsage([
                'user_id' => Auth::id(),
                'lesson_id' => $lesson->id,
                'feature' => 'lesson_narration',
                'action' => 'gemini_tts_synthesize',
                'provider' => 'gemini',
                'model' => $model,
                'status' => 'success',
                'prompt_tokens' => $totalUsage['prompt_tokens'],
                'completion_tokens' => $totalUsage['completion_tokens'],
                'total_tokens' => $totalUsage['total_tokens'],
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'metadata' => [
                    'lesson_id' => $lesson->id,
                    'chunks_count' => count($chunks),
                    'tracks_count' => count($paths),
                    'voice_name' => $voiceName,
                ],
            ]);
        } catch (\Throwable $e) {
            $record->fill([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
            ])->save();

            throw $e;
        }

        return $record;
    }

    /**
     * Generate an isolated Gemini test sample for benchmark/testing.
     *
     * @return array{paths: array<int, string>, urls: array<int, string>, usage: array<string, mixed>, voice_name: string, model: string}
     */
    public function generateGeminiTest(Lesson $lesson): array
    {
        $apiKey = $this->getGeminiApiKey();

        if ($apiKey === '') {
            throw new RuntimeException('Gemini API key is missing. Set GEMINI_API_KEY in .env or Admin Settings.');
        }

        $this->checkRateLimits($lesson);

        $segments = $this->buildNarrationSegments($lesson);

        if ($segments === []) {
            throw new RuntimeException('No lesson text was available for Gemini narration.');
        }

        $voiceName = $this->getGeminiVoiceName();
        $model = $this->getGeminiModel();
        $chunkDelay = $this->getGeminiChunkDelay();
        $chunks = $this->chunkSegments($segments, $this->getGeminiChunkChars());
        $disk = config('lesson_narration.storage_disk', 'private');
        $directory = trim((string) config('lesson_narration.gemini.test_storage_path', 'lesson-narrations/gemini-tests'), '/')
            . '/' . $lesson->id . '/' . now()->format('Ymd-His');
        $paths = [];
        $usage = ['prompt_tokens' => 0, 'audio_tokens' => 0, 'total_tokens' => 0, 'estimated_duration_seconds' => 0];

        foreach ($chunks as $index => $chunk) {
            if ($index > 0 && $chunkDelay > 0) {
                sleep($chunkDelay);
            }

            [$pcm, $chunkUsage] = $this->synthesizeGeminiChunk($chunk, $apiKey, $model, $voiceName, $lesson);
            $file = sprintf('part-%03d.wav', $index + 1);
            $path = "{$directory}/{$file}";

            Storage::disk($disk)->put($path, $this->pcmToWav($pcm));
            $paths[] = $path;
            $usage['prompt_tokens'] += $chunkUsage['prompt_tokens'];
            $usage['audio_tokens'] += $chunkUsage['audio_tokens'];
            $usage['total_tokens'] += $chunkUsage['total_tokens'];
            $usage['estimated_duration_seconds'] += strlen($pcm) / (24000 * 2);
        }

        return [
            'paths' => $paths,
            'urls' => $this->audioUrls($paths, $lesson),
            'usage' => $usage,
            'voice_name' => $voiceName,
            'model' => $model,
        ];
    }

    public function buildNarrationSegments(Lesson $lesson): array
    {
        $html = $this->normalizeLessonHtml($lesson->content ?? '');
        $segments = [];

        $title = $this->cleanText($lesson->title ?? '');
        if ($title !== '') {
            $segments[] = $this->cleanText("{$title}। চলুন পাঠটি বিস্তারিত পড়ি।");
        }

        if (class_exists(\DOMDocument::class)) {
            $doc = new \DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
            libxml_clear_errors();

            $body = $doc->getElementsByTagName('body')->item(0) ?: $doc;

            foreach ($body->childNodes as $child) {
                $this->traverseNarrationNode($child, $segments);
            }
        }

        $segments = array_values(array_filter(array_map(fn (string $segment) => $this->cleanText($segment), $segments)));

        if (empty($segments)) {
            $plain = $this->cleanText(strip_tags($html));
            if ($plain !== '') {
                $segments[] = $plain;
            }
        }

        return $segments;
    }

    /**
     * Synthesize text chunk to raw PCM audio using Gemini TTS API with rate limit tracking.
     *
     * @return array{0: string, 1: array{prompt_tokens: int, audio_tokens: int, total_tokens: int}}
     */
    public function synthesizeGeminiChunk(string $text, string $apiKey, string $model, string $voiceName, ?Lesson $lesson = null): array
    {
        $timeout = (int) config('lesson_narration.gemini.timeout', 120);
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->post($endpoint, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => $this->getGeminiPrompt() . "\n\n{$text}",
                    ]],
                ]],
                'generationConfig' => [
                    'responseModalities' => ['AUDIO'],
                    'speechConfig' => [
                        'voiceConfig' => [
                            'prebuiltVoiceConfig' => ['voiceName' => $voiceName],
                        ],
                        'languageCode' => 'bn-IN',
                    ],
                ],
            ]);

        // Check for Rate Limit (HTTP 429 or RESOURCE_EXHAUSTED body)
        $body = $response->body();
        $isRateLimited = $response->status() === 429
            || str_contains($body, 'RESOURCE_EXHAUSTED')
            || str_contains($body, 'RATE_LIMIT_EXCEEDED')
            || str_contains($body, 'rateLimitExceeded')
            || str_contains($body, 'Resource has been exhausted');

        if ($isRateLimited) {
            $retryAfter = $response->header('Retry-After');
            $retryText = $retryAfter ? "অনুগ্রহ করে {$retryAfter} সেকেন্ড অপেক্ষা করে" : 'অনুগ্রহ করে ৩০-৬০ সেকেন্ড অপেক্ষা করে';

            // Create dedicated rate limit audit log
            $this->logUsage([
                'user_id' => Auth::id(),
                'lesson_id' => $lesson?->id,
                'feature' => 'lesson_narration',
                'action' => 'gemini_tts_synthesize',
                'provider' => 'gemini',
                'model' => $model,
                'status' => 'rate_limited',
                'error_message' => "Google AI Studio Rate Limit Reached (429): {$body}",
                'metadata' => [
                    'http_status' => $response->status(),
                    'retry_after' => $retryAfter,
                    'lesson_id' => $lesson?->id,
                    'model' => $model,
                    'voice_name' => $voiceName,
                    'chunk_chars' => mb_strlen($text),
                    'rate_limit_doc' => 'https://aistudio.google.com/docs/rate-limits',
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            Log::warning("Google Gemini TTS API Rate Limit Hit (429)", [
                'lesson_id' => $lesson?->id,
                'model' => $model,
                'http_status' => $response->status(),
                'retry_after' => $retryAfter,
                'response_body' => Str::limit($body, 300),
            ]);

            throw new RuntimeException("Google AI Studio রেট লিমিট (429 / Resource Exhausted) সীমা স্পর্শ করেছে। {$retryText} আবার চেষ্টা করুন।");
        }

        if (! $response->successful()) {
            $this->logUsage([
                'user_id' => Auth::id(),
                'lesson_id' => $lesson?->id,
                'feature' => 'lesson_narration',
                'action' => 'gemini_tts_synthesize',
                'provider' => 'gemini',
                'model' => $model,
                'status' => 'failed',
                'error_message' => "Gemini synthesis error ({$response->status()}): {$body}",
                'metadata' => ['http_status' => $response->status(), 'lesson_id' => $lesson?->id],
            ]);

            throw new RuntimeException('Gemini narration synthesis failed: ' . $body);
        }

        // Increment rate limit counters on success
        $this->incrementRateLimits();

        $data = (string) $response->json('candidates.0.content.parts.0.inlineData.data', '');

        if ($data === '') {
            throw new RuntimeException('Gemini narration synthesis returned no audio data.');
        }

        $pcm = base64_decode($data, true);

        if ($pcm === false || $pcm === '') {
            throw new RuntimeException('Gemini narration synthesis returned invalid audio data.');
        }

        $metadata = (array) $response->json('usageMetadata', []);

        return [$pcm, [
            'prompt_tokens' => (int) ($metadata['promptTokenCount'] ?? 0),
            'audio_tokens' => (int) ($metadata['candidatesTokenCount'] ?? 0),
            'total_tokens' => (int) ($metadata['totalTokenCount'] ?? 0),
        ]];
    }

    /**
     * Check if current minute or daily rate limit thresholds are exceeded.
     */
    public function checkRateLimits(?Lesson $lesson = null): void
    {
        $maxRpm = $this->getGeminiMaxRpm();
        $maxRpd = $this->getGeminiMaxRpd();

        $minKey = 'gemini_tts_rpm_' . now()->format('YmdHi');
        $dayKey = 'gemini_tts_rpd_' . now()->format('Ymd');

        $currentRpm = (int) Cache::get($minKey, 0);
        $currentRpd = (int) Cache::get($dayKey, 0);

        if ($currentRpm >= $maxRpm) {
            $this->logUsage([
                'user_id' => Auth::id(),
                'lesson_id' => $lesson?->id,
                'feature' => 'lesson_narration',
                'action' => 'rate_limit_precheck',
                'provider' => 'gemini',
                'status' => 'rate_limited',
                'error_message' => "Local RPM limit exceeded: {$currentRpm}/{$maxRpm} requests this minute.",
                'metadata' => ['current_rpm' => $currentRpm, 'max_rpm' => $maxRpm],
            ]);

            throw new RuntimeException("Google Gemini API রেট লিমিট সতর্কতা: প্রতি মিনিটে সর্বোচ্চ {$maxRpm}টি রিকোয়েস্টের কোটা পূর্ণ হয়েছে। অনুগ্রহ করে ১ মিনিট পর আবার চেষ্টা করুন।");
        }

        if ($currentRpd >= $maxRpd) {
            $this->logUsage([
                'user_id' => Auth::id(),
                'lesson_id' => $lesson?->id,
                'feature' => 'lesson_narration',
                'action' => 'rate_limit_precheck',
                'provider' => 'gemini',
                'status' => 'rate_limited',
                'error_message' => "Local daily RPD limit exceeded: {$currentRpd}/{$maxRpd} requests today.",
                'metadata' => ['current_rpd' => $currentRpd, 'max_rpd' => $maxRpd],
            ]);

            throw new RuntimeException("Google Gemini API দৈনিক কোটা পূর্ণ হয়েছে (সর্বোচ্চ {$maxRpd}টি/দিন)। অনুগ্রহ করে পরবর্তীতে চেষ্টা করুন।");
        }
    }

    public function incrementRateLimits(): void
    {
        $minKey = 'gemini_tts_rpm_' . now()->format('YmdHi');
        $dayKey = 'gemini_tts_rpd_' . now()->format('Ymd');

        Cache::put($minKey, (int) Cache::get($minKey, 0) + 1, 70);
        Cache::put($dayKey, (int) Cache::get($dayKey, 0) + 1, 86400 * 2);
    }

    /**
     * Package raw 24kHz 16-bit Mono PCM bytes into a standard playable WAV file.
     */
    public function pcmToWav(string $pcm, int $sampleRate = 24000): string
    {
        $channels = 1;
        $bitsPerSample = 16;
        $blockAlign = $channels * ($bitsPerSample / 8);
        $byteRate = $sampleRate * $blockAlign;

        return 'RIFF'
            . pack('V', 36 + strlen($pcm))
            . 'WAVEfmt '
            . pack('VvvVVvv', 16, 1, $channels, $sampleRate, $byteRate, $blockAlign, $bitsPerSample)
            . 'data'
            . pack('V', strlen($pcm))
            . $pcm;
    }

    public function formatVoiceLabel(string $voiceName): string
    {
        $voices = [
            'Kore'   => 'Kore (মহিলা ভয়েস)',
            'Puck'   => 'Puck (পুরুষ ভয়েস)',
            'Fenrir' => 'Fenrir (গম্ভীর পুরুষ ভয়েস)',
            'Aoede'  => 'Aoede (মহিলা ভয়েস)',
            'Leda'   => 'Leda (মহিলা ভয়েস)',
            'Zephyr' => 'Zephyr (মহিলা ভয়েস)',
            'Charon' => 'Charon (পুরুষ ভয়েস)',
        ];

        return $voices[$voiceName] ?? "{$voiceName} (Gemini ভয়েস)";
    }

    public function sourceHash(Lesson $lesson): string
    {
        $payload = implode('|', [
            $lesson->id,
            $lesson->title ?? '',
            $lesson->content ?? '',
            'gemini',
            config('lesson_narration.locale', 'bn-IN'),
            $this->getGeminiVoiceName(),
            $this->getGeminiModel(),
        ]);

        return sha1($payload);
    }

    public function chunkSegments(array $segments, int $maxChars = 850): array
    {
        $chunks = [];
        $current = '';

        foreach ($segments as $segment) {
            foreach ($this->splitNarrationSegment($segment, $maxChars) as $part) {
                if ($current === '') {
                    $current = $part;
                    continue;
                }

                if (mb_strlen($current . ' ' . $part) <= $maxChars) {
                    $current .= ' ' . $part;
                } else {
                    $chunks[] = $current;
                    $current = $part;
                }
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function splitNarrationSegment(string $segment, int $maxChars): array
    {
        $segment = trim($segment);

        if ($segment === '') {
            return [];
        }

        if (mb_strlen($segment) <= $maxChars) {
            return [$segment];
        }

        $parts = [];
        $current = '';

        foreach (preg_split('/\s+/u', $segment) ?: [] as $word) {
            if (mb_strlen($word) > $maxChars) {
                if ($current !== '') {
                    $parts[] = $current;
                    $current = '';
                }

                while (mb_strlen($word) > $maxChars) {
                    $parts[] = mb_substr($word, 0, $maxChars);
                    $word = mb_substr($word, $maxChars);
                }
            }

            if ($current !== '' && mb_strlen($current . ' ' . $word) > $maxChars) {
                $parts[] = $current;
                $current = $word;
                continue;
            }

            $current .= $current === '' ? $word : ' ' . $word;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    private function logUsage(array $attributes): void
    {
        try {
            AiUsageLog::create($attributes);
        } catch (\Throwable $e) {
            Log::error('Failed to write AiUsageLog: ' . $e->getMessage());
        }
    }

    private function getGeminiApiKey(): string
    {
        $settingKey = Setting::get('gemini_api_key');
        if (! empty($settingKey)) {
            return (string) $settingKey;
        }

        return (string) config('lesson_narration.gemini.api_key', '');
    }

    private function getGeminiModel(): string
    {
        $settingModel = Setting::get('gemini_tts_model');
        if (! empty($settingModel)) {
            return (string) $settingModel;
        }

        return (string) config('lesson_narration.gemini.model', 'gemini-2.5-flash-preview-tts');
    }

    private function getGeminiVoiceName(): string
    {
        $settingVoice = Setting::get('gemini_tts_voice_name');
        if (! empty($settingVoice)) {
            return (string) $settingVoice;
        }

        return (string) config('lesson_narration.gemini.voice_name', 'Kore');
    }

    private function getGeminiPrompt(): string
    {
        $settingPrompt = Setting::get('gemini_tts_prompt');
        if (! empty($settingPrompt)) {
            return (string) $settingPrompt;
        }

        return "Read the following educational lesson text clearly, smoothly, and naturally in Bengali. Pronounce English technical terms, software concepts, and Bengali sentences fluently without skipping words or halting on technical names.";
    }

    private function getGeminiChunkChars(): int
    {
        $settingChars = Setting::get('gemini_tts_chunk_chars');
        if (! empty($settingChars)) {
            return (int) $settingChars;
        }

        return (int) config('lesson_narration.chunk_chars', 850);
    }

    private function getGeminiMaxRpm(): int
    {
        $settingRpm = Setting::get('gemini_tts_max_rpm');
        if ($settingRpm !== null && $settingRpm !== '') {
            return max(1, (int) $settingRpm);
        }

        return (int) config('lesson_narration.gemini.max_rpm', 10);
    }

    private function getGeminiMaxRpd(): int
    {
        $settingRpd = Setting::get('gemini_tts_max_rpd');
        if ($settingRpd !== null && $settingRpd !== '') {
            return max(1, (int) $settingRpd);
        }

        return (int) config('lesson_narration.gemini.max_rpd', 100);
    }

    private function getGeminiChunkDelay(): int
    {
        $settingDelay = Setting::get('gemini_tts_chunk_delay');
        if ($settingDelay !== null && $settingDelay !== '') {
            return max(0, (int) $settingDelay);
        }

        return (int) config('lesson_narration.gemini.chunk_delay_seconds', 3);
    }

    private function audioUrls(array $paths, Lesson $lesson): array
    {
        return collect($paths)
            ->filter()
            ->keys()
            ->map(fn (int $index) => route('lessons.narration.audio', [
                'vault' => $lesson->vault,
                'lesson' => $lesson,
                'track' => $index,
            ]))
            ->values()
            ->all();
    }

    private function audioPath(Lesson $lesson, string $sourceHash, int $index): string
    {
        $safeHash = substr($sourceHash, 0, 16);
        $directory = trim(config('lesson_narration.storage_path', 'lesson-narrations'), '/');
        $file = sprintf('part-%03d.wav', $index + 1);

        return "{$directory}/{$lesson->id}/{$safeHash}/{$file}";
    }

    private function deleteNarrationFiles(array $paths): void
    {
        $paths = array_values(array_filter($paths));

        if ($paths === []) {
            return;
        }

        Storage::disk(config('lesson_narration.storage_disk', 'private'))->delete($paths);
    }

    private function normalizeLessonHtml(string $html): string
    {
        $html = preg_replace('/\[\[READMORE\]\]/i', '', $html) ?? $html;

        return $html;
    }

    private function traverseNarrationNode(\DOMNode $node, array &$segments): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $this->cleanText($node->nodeValue ?? '');
            if ($text !== '') {
                $segments[] = $text;
            }
            return;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, ['script', 'style'], true)) {
            return;
        }

        // Code block container (pre)
        if ($tag === 'pre') {
            $codeText = $node->textContent ?? '';
            $converted = $this->convertCodeBlockToNarration($codeText);
            if ($converted !== '') {
                $segments[] = $converted;
            }
            return;
        }

        // Headings
        if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $text = $this->cleanText($node->textContent ?? '');
            if ($text !== '') {
                $segments[] = $text;
            }
            return;
        }

        // Paragraphs, list items, blockquotes (can have inline code, strong, em, etc.)
        if (in_array($tag, ['p', 'li', 'blockquote'], true)) {
            $hasPre = false;
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'pre') {
                    $hasPre = true;
                    break;
                }
            }

            if (! $hasPre) {
                $text = $this->cleanText($node->textContent ?? '');
                if ($text !== '') {
                    $segments[] = $text;
                }
                return;
            }
        }

        foreach ($node->childNodes as $child) {
            $this->traverseNarrationNode($child, $segments);
        }
    }

    /**
     * Convert code snippets, terminal commands, or ASCII diagrams into fluent spoken text.
     */
    public function convertCodeBlockToNarration(string $rawCode): string
    {
        $rawCode = html_entity_decode($rawCode, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rawCode = strip_tags($rawCode);
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $rawCode) ?: []),
            fn (string $l) => $l !== ''
        ));

        if (empty($lines)) {
            return '';
        }

        // 1. Check if this is an architecture diagram or flow chart with arrows
        $arrowSymbols = ['↓', '⬇', '▼', '->', '-->', '=>', '➔', '➜', '|', '├──', '└──'];
        $hasArrows = false;
        foreach ($lines as $line) {
            foreach ($arrowSymbols as $sym) {
                if (str_contains($line, $sym)) {
                    $hasArrows = true;
                    break 2;
                }
            }
        }

        if ($hasArrows) {
            $steps = [];
            foreach ($lines as $line) {
                $subParts = preg_split('/\s*(?:->|-->|=>|↓|⬇|▼|➔|➜|├──|└──|\|)\s*/u', $line) ?: [$line];
                foreach ($subParts as $sub) {
                    $cleanSub = trim(preg_replace('/^[0-9]+[\.\-\)]\s*/u', '', $sub));
                    if ($cleanSub !== '') {
                        $cleanSub = preg_replace('/\s*\+\s*/u', ' এবং ', $cleanSub);
                        $steps[] = $cleanSub;
                    }
                }
            }

            if (count($steps) >= 2) {
                $formatted = 'ধারাবাহিক ফ্লো অনুযায়ী: প্রথমে ' . $steps[0];
                for ($i = 1; $i < count($steps) - 1; $i++) {
                    $formatted .= ', এরপর ' . $steps[$i];
                }
                $formatted .= ', এবং শেষে ' . $steps[count($steps) - 1] . '।';

                return $this->naturalizeForSpeech($formatted);
            } elseif (count($steps) === 1) {
                return $this->naturalizeForSpeech($steps[0] . '।');
            }
        }

        // 2. Terminal commands
        if (count($lines) <= 3) {
            $firstLine = $lines[0];
            if (preg_match('/^(php|composer|npm|npx|git|artisan|docker|yarn|pnpm|curl|mkdir|cd|cat|\$)\b/i', $firstLine)) {
                $cmd = ltrim(implode('; ', $lines), '$ ');
                return $this->naturalizeForSpeech("টার্মিনাল কমান্ড: {$cmd}।");
            }
        }

        // 3. Code snippets: if long (> 8 lines), summarize cleanly
        if (count($lines) > 8) {
            $sampleLines = array_slice($lines, 0, 4);
            $cleanSamples = [];
            foreach ($sampleLines as $line) {
                $cl = $this->cleanCodeLineForSpeech($line);
                if ($cl !== '') {
                    $cleanSamples[] = $cl;
                }
            }
            $sampleText = implode(', ', $cleanSamples);

            return $this->naturalizeForSpeech("কোড উদাহরণে মূল অংশগুলো হলো: {$sampleText}, ইত্যাদি। বিস্তারিত কোডটি লেসনে দেখে নিতে পারেন।");
        }

        // 4. Short code snippet (1 to 8 lines)
        $spokenLines = [];
        foreach ($lines as $line) {
            $cl = $this->cleanCodeLineForSpeech($line);
            if ($cl !== '') {
                $spokenLines[] = $cl;
            }
        }

        if (empty($spokenLines)) {
            return '';
        }

        return $this->naturalizeForSpeech("কোড উদাহরণ: " . implode('; ', $spokenLines) . '।');
    }

    private function cleanCodeLineForSpeech(string $line): string
    {
        $line = trim($line);
        if (in_array($line, ['<?php', '?>', '{', '}', '};', '];', '(', ')'], true)) {
            return '';
        }

        $line = preg_replace('/^\/\/\s*/', 'নোট: ', $line);
        $line = preg_replace('/^#\s*/', 'নোট: ', $line);
        $line = str_replace('->', ' এর ', $line);
        $line = str_replace('::', ' এর ', $line);
        $line = str_replace('=>', ' অ্যারো ', $line);
        $line = str_replace([';', '{', '}'], ' ', $line);
        $line = preg_replace('/\s+/u', ' ', $line);

        return trim($line);
    }

    /**
     * Clean raw text and convert Unicode symbols/arrows into natural spoken Bengali phrases.
     */
    public function naturalizeForSpeech(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);

        $replacements = [
            '↓' => ' এরপর ',
            '⬇' => ' এরপর ',
            '▼' => ' এরপর ',
            '➔' => ' এরপর ',
            '➜' => ' এরপর ',
            '↑' => ' পূর্ববর্তী ',
            '⬆' => ' পূর্ববর্তী ',
            '▲' => ' পূর্ববর্তী ',
            '←' => ' থেকে ',
            '⬅' => ' থেকে ',
            '↔' => ' ও ',
            '💡' => 'টিপস: ',
            '⚠️' => 'সতর্কতা: ',
            '📌' => 'নোট: ',
            '✓' => 'সঠিক ',
            '✔' => 'সঠিক ',
            '☑' => 'সঠিক ',
            '❌' => 'ভুল ',
            '✖' => 'ভুল ',
            '✗' => 'ভুল ',
            '├──' => ' ',
            '└──' => ' ',
            '│' => ' ',
            '──' => ' ',
            '---' => ' ',
            '===' => ' ',
            '`' => '',
        ];

        $text = strtr($text, $replacements);

        // Replace + between words like "HTML + CSS" with "এবং"
        $text = preg_replace('/([a-zA-Z\x{0980}-\x{09FF}])\s*\+\s*([a-zA-Z\x{0980}-\x{09FF}])/u', '$1 এবং $2', $text) ?? $text;

        // Replace standalone arrows like "->" or "-->"
        $text = preg_replace('/\s*--?>\s*/u', ' এরপর ', $text) ?? $text;
        $text = preg_replace('/\s*<--?\s*/u', ' থেকে ', $text) ?? $text;

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function cleanText(string $text): string
    {
        return $this->naturalizeForSpeech($text);
    }
}
