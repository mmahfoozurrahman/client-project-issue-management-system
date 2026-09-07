<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminSiteSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:255'],
            'issue_daily_target' => ['required', 'integer', 'min:1', 'max:50'],
            'issue_stale_days' => ['required', 'integer', 'min:1', 'max:60'],
            'issue_critical_days' => ['required', 'integer', 'min:1', 'max:120', 'gte:issue_stale_days'],

            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'gemini_tts_model' => ['nullable', 'string', 'max:255'],
            'gemini_tts_voice_name' => ['nullable', 'string', Rule::in(['Kore', 'Puck', 'Fenrir', 'Aoede', 'Leda', 'Zephyr', 'Charon'])],
            'gemini_tts_prompt' => ['nullable', 'string', 'max:2000'],
            'gemini_tts_chunk_chars' => ['nullable', 'integer', 'min:200', 'max:8000'],
            'gemini_tts_max_rpm' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'gemini_tts_max_rpd' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'gemini_tts_chunk_delay' => ['nullable', 'integer', 'min:0', 'max:60'],
        ];
    }
}
