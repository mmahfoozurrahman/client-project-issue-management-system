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

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Site settings updated successfully.');
    }
}
