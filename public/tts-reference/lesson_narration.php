<?php

return [
    'provider' => env('LESSON_NARRATION_PROVIDER', 'gemini'),
    'locale' => env('LESSON_NARRATION_LOCALE', 'bn-IN'),
    'chunk_chars' => (int) env('LESSON_NARRATION_CHUNK_CHARS', 4800),
    'storage_disk' => env('LESSON_NARRATION_DISK', 'private'),
    'storage_path' => env('LESSON_NARRATION_STORAGE_PATH', 'lesson-narrations'),
    'poll_interval_ms' => (int) env('LESSON_NARRATION_POLL_INTERVAL_MS', 3000),
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_TTS_MODEL', 'gemini-2.5-flash-preview-tts'),
        'voice_name' => env('GEMINI_TTS_VOICE_NAME', 'Kore'),
        'timeout' => (int) env('GEMINI_TTS_TIMEOUT', 120),
        'test_storage_path' => env('GEMINI_TTS_TEST_STORAGE_PATH', 'lesson-narrations/gemini-tests'),
        'max_rpm' => (int) env('GEMINI_TTS_MAX_RPM', 10),
        'max_rpd' => (int) env('GEMINI_TTS_MAX_RPD', 100),
        'chunk_delay_seconds' => (int) env('GEMINI_TTS_CHUNK_DELAY_SECONDS', 3),
    ],
];
