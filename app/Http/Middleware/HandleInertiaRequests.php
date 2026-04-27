<?php

namespace App\Http\Middleware;

use App\Models\Issue;
use App\Models\SiteMeta;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $staleDays = max((int) SiteMeta::value('issue_stale_days', (string) config('app.issue_stale_days', 3)), 1);
        $criticalDays = max((int) SiteMeta::value('issue_critical_days', (string) config('app.issue_critical_days', 7)), $staleDays);
        $pendingNudgeCount = 0;
        $pendingCriticalCount = 0;

        if ($request->user()) {
            $pendingNudgeCount = Issue::query()
                ->whereNull('done_at')
                ->where('status', '!=', 'done')
                ->where('updated_at', '<=', Carbon::now()->subDays($staleDays))
                ->count();

            $pendingCriticalCount = Issue::query()
                ->whereNull('done_at')
                ->where('status', '!=', 'done')
                ->where('updated_at', '<=', Carbon::now()->subDays($criticalDays))
                ->count();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email', 'is_admin', 'avatar_url']),
            ],
            'app' => [
                'site_name' => SiteMeta::value('site_name', 'Issue Tracker'),
                'pending_nudge_count' => $pendingNudgeCount,
                'pending_critical_count' => $pendingCriticalCount,
                'issue_stale_days' => $staleDays,
                'issue_critical_days' => $criticalDays,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
