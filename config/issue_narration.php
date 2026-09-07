<?php

return [
    'locale' => env('ISSUE_NARRATION_LOCALE', 'bn-IN'),
    'max_execution_seconds' => (int) env('ISSUE_NARRATION_MAX_EXECUTION_SECONDS', 300),
    'chunk_chars' => (int) env('ISSUE_NARRATION_CHUNK_CHARS', 4800),
    'storage_disk' => env('ISSUE_NARRATION_DISK', 'local'),
    'storage_path' => env('ISSUE_NARRATION_STORAGE_PATH', 'issue-narrations'),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_TTS_MODEL', 'gemini-2.5-flash-preview-tts'),
        'voice_name' => env('GEMINI_TTS_VOICE_NAME', 'Kore'),
        'timeout' => (int) env('GEMINI_TTS_TIMEOUT', 120),
        'prompt' => env('GEMINI_TTS_PROMPT', 'Read the following text clearly, smoothly, and naturally in Bengali. Pronounce English technical terms fluently without skipping words or halting on technical names.'),
        'max_rpm' => (int) env('GEMINI_TTS_MAX_RPM', 10),
        'max_rpd' => (int) env('GEMINI_TTS_MAX_RPD', 100),
        'chunk_delay_seconds' => (int) env('GEMINI_TTS_CHUNK_DELAY_SECONDS', 3),
    ],
];
