<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminSiteSettingsUpdateRequest;
use App\Models\SiteMeta;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SiteSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'site_name' => SiteMeta::value('site_name', 'Issue Tracker'),
                'issue_daily_target' => (int) SiteMeta::value('issue_daily_target', (string) config('app.issue_daily_target', 3)),
                'issue_stale_days' => (int) SiteMeta::value('issue_stale_days', (string) config('app.issue_stale_days', 3)),
                'issue_critical_days' => (int) SiteMeta::value('issue_critical_days', (string) config('app.issue_critical_days', 7)),

                'gemini_api_key_set' => filled(SiteMeta::value('gemini_api_key')) || filled(config('issue_narration.gemini.api_key')),
                'gemini_tts_model' => SiteMeta::value('gemini_tts_model', (string) config('issue_narration.gemini.model')),
                'gemini_tts_voice_name' => SiteMeta::value('gemini_tts_voice_name', (string) config('issue_narration.gemini.voice_name')),
                'gemini_tts_prompt' => SiteMeta::value('gemini_tts_prompt', (string) config('issue_narration.gemini.prompt')),
                'gemini_tts_chunk_chars' => (int) SiteMeta::value('gemini_tts_chunk_chars', (string) config('issue_narration.chunk_chars')),
                'gemini_tts_max_rpm' => (int) SiteMeta::value('gemini_tts_max_rpm', (string) config('issue_narration.gemini.max_rpm')),
                'gemini_tts_max_rpd' => (int) SiteMeta::value('gemini_tts_max_rpd', (string) config('issue_narration.gemini.max_rpd')),
                'gemini_tts_chunk_delay' => (int) SiteMeta::value('gemini_tts_chunk_delay', (string) config('issue_narration.gemini.chunk_delay_seconds')),
            ],
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Site Settings'],
            ],
        ]);
    }

    public function update(AdminSiteSettingsUpdateRequest $request): RedirectResponse
    {
        SiteMeta::query()->updateOrCreate(
            ['key' => 'site_name'],
            ['value' => $request->validated('site_name')]
        );

        SiteMeta::query()->updateOrCreate(
            ['key' => 'issue_daily_target'],
            ['value' => (string) $request->validated('issue_daily_target')]
        );

        SiteMeta::query()->updateOrCreate(
            ['key' => 'issue_stale_days'],
            ['value' => (string) $request->validated('issue_stale_days')]
        );

        SiteMeta::query()->updateOrCreate(
            ['key' => 'issue_critical_days'],
            ['value' => (string) $request->validated('issue_critical_days')]
        );

        // Only overwrite the stored key when the admin actually typed a new one.
        if (filled($request->validated('gemini_api_key'))) {
            SiteMeta::query()->updateOrCreate(
                ['key' => 'gemini_api_key'],
                ['value' => $request->validated('gemini_api_key')]
            );
        }

        foreach ([
            'gemini_tts_model',
            'gemini_tts_voice_name',
            'gemini_tts_prompt',
            'gemini_tts_chunk_chars',
            'gemini_tts_max_rpm',
            'gemini_tts_max_rpd',
            'gemini_tts_chunk_delay',
        ] as $key) {
            SiteMeta::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $request->validated($key)]
            );
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Site settings updated successfully.');
    }
}
