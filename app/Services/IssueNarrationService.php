<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\IssueNarration;
use App\Models\SiteMeta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class IssueNarrationService
{
    public function currentState(Issue $issue): array
    {
        $issue->loadMissing('narration');
        $record = $issue->narration;
        $sourceHash = $this->sourceHash($issue);
        $hashMatches = $record && hash_equals($record->source_hash ?? '', $sourceHash);
        $status = $record?->status ?? 'idle';

        if ($record && ! $hashMatches) {
            $status = 'stale';
        }

        // A job that died mid-run (e.g. killed by the queue worker's own timeout) never
        // gets to persist a failure — self-heal so the UI isn't stuck polling forever.
        if (
            $record
            && in_array($status, ['queued', 'processing'], true)
            && $record->requested_at
            && $record->requested_at->lt(now()->subMinutes(35))
        ) {
            $status = 'failed';
            $record->fill([
                'status' => 'failed',
                'error_message' => 'Narration generation timed out. Please try again.',
            ])->save();
        }

        $audioUrls = $record && $hashMatches && $record->status === 'ready'
            ? $this->audioUrls($record->audio_paths ?? [], $issue)
            : [];
        $missingAudio = $record && $hashMatches && $record->status === 'ready' && empty($audioUrls);
        $isAvailable = $record && $hashMatches && $record->status === 'ready' && ! $missingAudio;

        return [
            'provider' => $record?->provider ?? 'gemini',
            'locale' => $record?->locale ?? config('issue_narration.locale', 'bn-IN'),
            'voice_name' => $record?->voice_name ?? $this->getGeminiVoiceName(),
            'voice_label' => $this->formatVoiceLabel($record?->voice_name ?? $this->getGeminiVoiceName()),
            'status' => $status,
            'is_available' => $isAvailable,
            'needs_refresh' => ! $record || ! $hashMatches || in_array($status, ['failed', 'stale'], true) || $missingAudio,
            'audio_urls' => $audioUrls,
            'track_count' => count($audioUrls),
            'requested_at' => $record?->requested_at?->toIso8601String(),
            'generated_at' => $record?->generated_at?->toIso8601String(),
            'error_message' => $record?->error_message,
        ];
    }

    public function generate(Issue $issue, bool $force = false): IssueNarration
    {
        $record = IssueNarration::firstOrNew(['issue_id' => $issue->id]);
        $segments = $this->buildNarrationSegments($issue);
        $sourceHash = $this->sourceHash($issue);
        $locale = config('issue_narration.locale', 'bn-IN');

        if (
            ! $force
            && $record->exists
            && $record->status === 'ready'
            && hash_equals($record->source_hash ?? '', $sourceHash)
            && ! empty($record->audio_paths)
        ) {
            return $record;
        }

        if ($record->exists && ! empty($record->audio_paths)) {
            $this->deleteNarrationFiles($record->audio_paths);
        }

        if (empty($segments)) {
            $record->fill([
                'provider' => 'gemini',
                'locale' => $locale,
                'source_hash' => $sourceHash,
                'status' => 'failed',
                'audio_paths' => null,
                'error_message' => 'No issue text was available for narration.',
                'requested_at' => now(),
            ])->save();

            return $record;
        }

        $apiKey = $this->getGeminiApiKey();

        if ($apiKey === '') {
            $record->fill([
                'provider' => 'gemini',
                'locale' => $locale,
                'source_hash' => $sourceHash,
                'status' => 'failed',
                'audio_paths' => null,
                'error_message' => 'Gemini API key is missing. Set it in Admin Settings or GEMINI_API_KEY in .env.',
                'requested_at' => now(),
            ])->save();

            return $record;
        }

        $this->checkRateLimits();

        $voiceName = $this->getGeminiVoiceName();
        $model = $this->getGeminiModel();
        $chunkDelay = $this->getGeminiChunkDelay();

        $record->fill([
            'provider' => 'gemini',
            'locale' => $locale,
            'voice_name' => $voiceName,
            'source_hash' => $sourceHash,
            'status' => 'processing',
            'audio_paths' => null,
            'error_message' => null,
            'requested_at' => now(),
        ])->save();

        $chunks = $this->chunkSegments($segments, $this->getGeminiChunkChars());
        $disk = config('issue_narration.storage_disk', 'local');
        $paths = [];

        try {
            foreach ($chunks as $index => $chunk) {
                if ($index > 0 && $chunkDelay > 0) {
                    sleep($chunkDelay);
                }

                $pcm = $this->synthesizeGeminiChunk($chunk, $apiKey, $model, $voiceName);
                $path = $this->audioPath($issue, $sourceHash, $index);

                Storage::disk($disk)->put($path, $this->pcmToWav($pcm));
                $paths[] = $path;
            }

            $record->fill([
                'status' => 'ready',
                'audio_paths' => $paths,
                'generated_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (\Throwable $e) {
            $this->deleteNarrationFiles($paths);

            $record->fill([
                'status' => 'failed',
                'audio_paths' => null,
                'error_message' => Str::limit($e->getMessage(), 1000),
            ])->save();

            throw $e;
        }

        return $record;
    }

    public function buildNarrationSegments(Issue $issue): array
    {
        $html = (string) ($issue->description ?? '');
        $segments = [];

        $title = $this->cleanText($issue->title ?? '');
        if ($title !== '') {
            $segments[] = $this->cleanText("{$title}। চলুন বিস্তারিত পড়ি।");
        }

        if (class_exists(\DOMDocument::class) && $html !== '') {
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

    public function synthesizeGeminiChunk(string $text, string $apiKey, string $model, string $voiceName): string
    {
        $timeout = (int) config('issue_narration.gemini.timeout', 120);
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

        $response = Http::timeout($timeout)
            ->connectTimeout(15)
            ->retry(1, 3000, fn (\Throwable $e) => $e instanceof \Illuminate\Http\Client\ConnectionException)
            ->acceptJson()
            ->post($endpoint, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => implode("\n\n", [
                            'TTS the following Bengali transcript using the requested speaking style. Return audio only.',
                            'Style: ' . $this->getGeminiPrompt(),
                            "Transcript:\n{$text}",
                        ]),
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

        $body = $response->body();
        $isRateLimited = $response->status() === 429
            || str_contains($body, 'RESOURCE_EXHAUSTED')
            || str_contains($body, 'RATE_LIMIT_EXCEEDED')
            || str_contains($body, 'rateLimitExceeded')
            || str_contains($body, 'Resource has been exhausted');

        if ($isRateLimited) {
            $retryAfter = $response->header('Retry-After');
            $retryText = $retryAfter ? "অনুগ্রহ করে {$retryAfter} সেকেন্ড অপেক্ষা করে" : 'অনুগ্রহ করে ৩০-৬০ সেকেন্ড অপেক্ষা করে';

            Log::warning('Gemini TTS API rate limit hit (429)', [
                'http_status' => $response->status(),
                'retry_after' => $retryAfter,
                'response_body' => Str::limit($body, 300),
            ]);

            throw new RuntimeException("Google AI Studio রেট লিমিট (429 / Resource Exhausted) সীমা স্পর্শ করেছে। {$retryText} আবার চেষ্টা করুন।");
        }

        if (! $response->successful()) {
            Log::error('Gemini narration synthesis failed', ['http_status' => $response->status(), 'body' => Str::limit($body, 500)]);

            throw new RuntimeException('Gemini narration synthesis failed: ' . $body);
        }

        $this->incrementRateLimits();

        $data = (string) $response->json('candidates.0.content.parts.0.inlineData.data', '');

        if ($data === '') {
            throw new RuntimeException('Gemini narration synthesis returned no audio data.');
        }

        $pcm = base64_decode($data, true);

        if ($pcm === false || $pcm === '') {
            throw new RuntimeException('Gemini narration synthesis returned invalid audio data.');
        }

        return $pcm;
    }

    public function checkRateLimits(): void
    {
        $maxRpm = $this->getGeminiMaxRpm();
        $maxRpd = $this->getGeminiMaxRpd();

        $minKey = 'gemini_tts_rpm_' . now()->format('YmdHi');
        $dayKey = 'gemini_tts_rpd_' . now()->format('Ymd');

        $currentRpm = (int) Cache::get($minKey, 0);
        $currentRpd = (int) Cache::get($dayKey, 0);

        if ($currentRpm >= $maxRpm) {
            throw new RuntimeException("Google Gemini API রেট লিমিট সতর্কতা: প্রতি মিনিটে সর্বোচ্চ {$maxRpm}টি রিকোয়েস্টের কোটা পূর্ণ হয়েছে। অনুগ্রহ করে ১ মিনিট পর আবার চেষ্টা করুন।");
        }

        if ($currentRpd >= $maxRpd) {
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
            'Kore'   => 'Kore (মহিলা ভয়েস)',
            'Puck'   => 'Puck (পুরুষ ভয়েস)',
            'Fenrir' => 'Fenrir (গম্ভীর পুরুষ ভয়েস)',
            'Aoede'  => 'Aoede (মহিলা ভয়েস)',
            'Leda'   => 'Leda (মহিলা ভয়েস)',
            'Zephyr' => 'Zephyr (মহিলা ভয়েস)',
            'Charon' => 'Charon (পুরুষ ভয়েস)',
        ];

        return $voices[$voiceName] ?? "{$voiceName} (Gemini ভয়েস)";
    }

    public function sourceHash(Issue $issue): string
    {
        $payload = implode('|', [
            $issue->id,
            $issue->title ?? '',
            $issue->description ?? '',
            'gemini',
            config('issue_narration.locale', 'bn-IN'),
            $this->getGeminiVoiceName(),
            $this->getGeminiModel(),
        ]);

        return sha1($payload);
    }

    public function chunkSegments(array $segments, int $maxChars = 4800): array
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

    private function getGeminiApiKey(): string
    {
        $value = SiteMeta::value('gemini_api_key');

        return $value !== null && $value !== '' ? $value : (string) config('issue_narration.gemini.api_key', '');
    }

    private function getGeminiModel(): string
    {
        $value = SiteMeta::value('gemini_tts_model');

        return $value !== null && $value !== '' ? $value : (string) config('issue_narration.gemini.model', 'gemini-2.5-flash-preview-tts');
    }

    private function getGeminiVoiceName(): string
    {
        $value = SiteMeta::value('gemini_tts_voice_name');

        return $value !== null && $value !== '' ? $value : (string) config('issue_narration.gemini.voice_name', 'Kore');
    }

    private function getGeminiPrompt(): string
    {
        $value = SiteMeta::value('gemini_tts_prompt');

        return $value !== null && $value !== '' ? $value : (string) config('issue_narration.gemini.prompt');
    }

    private function getGeminiChunkChars(): int
    {
        $value = SiteMeta::value('gemini_tts_chunk_chars');

        return $value !== null && $value !== '' ? (int) $value : (int) config('issue_narration.chunk_chars', 4800);
    }

    private function getGeminiMaxRpm(): int
    {
        $value = SiteMeta::value('gemini_tts_max_rpm');

        return $value !== null && $value !== '' ? max(1, (int) $value) : (int) config('issue_narration.gemini.max_rpm', 10);
    }

    private function getGeminiMaxRpd(): int
    {
        $value = SiteMeta::value('gemini_tts_max_rpd');

        return $value !== null && $value !== '' ? max(1, (int) $value) : (int) config('issue_narration.gemini.max_rpd', 100);
    }

    private function getGeminiChunkDelay(): int
    {
        $value = SiteMeta::value('gemini_tts_chunk_delay');

        return $value !== null && $value !== '' ? max(0, (int) $value) : (int) config('issue_narration.gemini.chunk_delay_seconds', 3);
    }

    private function audioUrls(array $paths, Issue $issue): array
    {
        return collect($paths)
            ->filter()
            ->keys()
            ->map(fn (int $index) => route('issues.narration.audio', ['issue' => $issue, 'track' => $index]))
            ->values()
            ->all();
    }

    private function audioPath(Issue $issue, string $sourceHash, int $index): string
    {
        $safeHash = substr($sourceHash, 0, 16);
        $directory = trim(config('issue_narration.storage_path', 'issue-narrations'), '/');
        $file = sprintf('part-%03d.wav', $index + 1);

        return "{$directory}/{$issue->id}/{$safeHash}/{$file}";
    }

    private function deleteNarrationFiles(array $paths): void
    {
        $paths = array_values(array_filter($paths));

        if ($paths === []) {
            return;
        }

        Storage::disk(config('issue_narration.storage_disk', 'local'))->delete($paths);
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

        if ($tag === 'pre') {
            $converted = $this->convertCodeBlockToNarration($node->textContent ?? '');
            if ($converted !== '') {
                $segments[] = $converted;
            }
            return;
        }

        if (in_array($tag, ['h2', 'h3'], true)) {
            $text = $this->cleanText($node->textContent ?? '');
            if ($text !== '') {
                $segments[] = $text;
            }
            return;
        }

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

        if (count($lines) > 8) {
            $sampleLines = array_slice($lines, 0, 4);
            $cleanSamples = array_values(array_filter(array_map([$this, 'cleanCodeLineForSpeech'], $sampleLines)));
            $sampleText = implode(', ', $cleanSamples);

            return $this->naturalizeForSpeech("কোড উদাহরণে মূল অংশগুলো হলো: {$sampleText}, ইত্যাদি।");
        }

        $spokenLines = array_values(array_filter(array_map([$this, 'cleanCodeLineForSpeech'], $lines)));

        if (empty($spokenLines)) {
            return '';
        }

        return $this->naturalizeForSpeech('কোড উদাহরণ: ' . implode('; ', $spokenLines) . '।');
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

    public function naturalizeForSpeech(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);

        $replacements = [
            '↓' => ' এরপর ', '⬇' => ' এরপর ', '▼' => ' এরপর ', '➔' => ' এরপর ', '➜' => ' এরপর ',
            '↑' => ' পূর্ববর্তী ', '⬆' => ' পূর্ববর্তী ', '▲' => ' পূর্ববর্তী ',
            '←' => ' থেকে ', '⬅' => ' থেকে ', '↔' => ' ও ',
            '💡' => 'টিপস: ', '⚠️' => 'সতর্কতা: ', '📌' => 'নোট: ',
            '✓' => 'সঠিক ', '✔' => 'সঠিক ', '☑' => 'সঠিক ',
            '❌' => 'ভুল ', '✖' => 'ভুল ', '✗' => 'ভুল ',
            '├──' => ' ', '└──' => ' ', '│' => ' ', '──' => ' ', '---' => ' ', '===' => ' ', '`' => '',
        ];

        $text = strtr($text, $replacements);
        $text = preg_replace('/([a-zA-Z\x{0980}-\x{09FF}])\s*\+\s*([a-zA-Z\x{0980}-\x{09FF}])/u', '$1 এবং $2', $text) ?? $text;
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
