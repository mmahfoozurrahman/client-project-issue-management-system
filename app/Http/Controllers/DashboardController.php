<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Issue;
use App\Models\Project;
use App\Models\SiteMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $accessibleIds = $user->accessibleProjectIds();

        $staleDays = max((int) SiteMeta::value('issue_stale_days', (string) config('app.issue_stale_days', 3)), 1);
        $criticalDays = max((int) SiteMeta::value('issue_critical_days', (string) config('app.issue_critical_days', 7)), $staleDays);
        $needsAttentionStartDays = min(
            $staleDays + max((int) floor(max($criticalDays - $staleDays, 1) / 2), 1),
            $criticalDays
        );

        $baseQuery = fn() => Issue::withoutGlobalScope('user_owned')
            ->whereIn('project_id', $accessibleIds)
            ->whereNull('parent_id');

        $statusIssues = collect(['inprogress', 'todo', 'done'])->mapWithKeys(
            function (string $status) use ($baseQuery) {
                $query = $baseQuery()
                    ->with([
                        'project:id,name,client_id',
                        'project.client' => function ($query) {
                            $query->withoutGlobalScope('user_owned')->select('id', 'name');
                        },
                        'images',
                        'tags'
                    ])
                    ->withCount(['subIssues', 'images'])
                    ->where('status', $status);

                $status === 'done'
                    ? $query->orderByDesc('updated_at')
                    : $query->latest();

                return [$status => $query->limit(12)->get()];
            }
        );

        $statusSummary = collect(['inprogress', 'todo', 'done'])->mapWithKeys(
            fn (string $status) => [$status => $baseQuery()->where('status', $status)->count()]
        );

        $weekly = collect(range(7, 0))->map(function (int $offset) use ($accessibleIds) {
            $start = Carbon::now()->startOfWeek()->subWeeks($offset);
            $end = (clone $start)->endOfWeek();

            return [
                'label' => $start->format('M d'),
                'created' => Issue::withoutGlobalScope('user_owned')->whereIn('project_id', $accessibleIds)->whereBetween('created_at', [$start, $end])->count(),
                'completed' => Issue::withoutGlobalScope('user_owned')->whereIn('project_id', $accessibleIds)->whereBetween('done_at', [$start, $end])->count(),
            ];
        })->values();

        $monthly = collect(range(5, 0))->map(function (int $offset) use ($accessibleIds) {
            $start = Carbon::now()->startOfMonth()->subMonths($offset);
            $end = (clone $start)->endOfMonth();

            return [
                'label' => $start->format('M Y'),
                'created' => Issue::withoutGlobalScope('user_owned')->whereIn('project_id', $accessibleIds)->whereBetween('created_at', [$start, $end])->count(),
                'completed' => Issue::withoutGlobalScope('user_owned')->whereIn('project_id', $accessibleIds)->whereBetween('done_at', [$start, $end])->count(),
            ];
        })->values();

        $pendingWatch = $baseQuery()
            ->whereNull('done_at')->where('status', '!=', 'done')
            ->where('updated_at', '<=', Carbon::now()->subDays($staleDays))
            ->where('updated_at', '>', Carbon::now()->subDays($needsAttentionStartDays))
            ->count();

        $pendingNeedsAttention = $baseQuery()
            ->whereNull('done_at')->where('status', '!=', 'done')
            ->where('updated_at', '<=', Carbon::now()->subDays($needsAttentionStartDays))
            ->where('updated_at', '>', Carbon::now()->subDays($criticalDays))
            ->count();

        $pendingCritical = $baseQuery()
            ->whereNull('done_at')->where('status', '!=', 'done')
            ->where('updated_at', '<=', Carbon::now()->subDays($criticalDays))
            ->count();

        $pendingFocusIssues = $baseQuery()
            ->with([
                'project:id,name,client_id',
                'project.client' => function ($query) {
                    $query->withoutGlobalScope('user_owned')->select('id', 'name');
                },
                'tags'
            ])
            ->whereNull('done_at')->where('status', '!=', 'done')
            ->where('updated_at', '<=', Carbon::now()->subDays($staleDays))
            ->orderBy('updated_at')
            ->limit(6)
            ->get();

        return Inertia::render('Dashboard', [
            'counts' => [
                'clients' => $user->is_admin ? Client::withoutGlobalScope('user_owned')->count() : Client::query()->count(),
                'projects' => count($accessibleIds),
                'issues' => $baseQuery()->count(),
            ],
            'statusIssues' => $statusIssues,
            'statusSummary' => $statusSummary,
            'analytics' => [
                'weekly' => $weekly,
                'monthly' => $monthly,
            ],
            'pendingNudges' => [
                'stale_days' => $staleDays,
                'critical_days' => $criticalDays,
                'watch' => $pendingWatch,
                'needs_attention' => $pendingNeedsAttention,
                'critical' => $pendingCritical,
                'focus_issues' => $pendingFocusIssues,
            ],
            'breadcrumbs' => [['label' => 'Home']],
            'authUser' => $user,
        ]);
    }
}
