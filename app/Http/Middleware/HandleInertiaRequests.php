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
        $staleDays = max((int) config('app.issue_stale_days', 7), 1);
        $pendingNudgeCount = 0;

        if ($request->user()) {
            $pendingNudgeCount = Issue::query()
                ->whereNull('done_at')
                ->where('status', '!=', 'done')
                ->where('updated_at', '<=', Carbon::now()->subDays($staleDays))
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
                'issue_stale_days' => $staleDays,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
