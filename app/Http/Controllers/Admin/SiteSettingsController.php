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

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Site settings updated successfully.');
    }
}
