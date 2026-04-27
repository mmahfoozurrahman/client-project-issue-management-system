<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Models\Client;
use App\Models\Issue;
use App\Models\Project;
use App\Models\SiteMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', function (Request $request) {
        $staleDays = max((int) SiteMeta::value('issue_stale_days', (string) config('app.issue_stale_days', 3)), 1);
        $criticalDays = max((int) SiteMeta::value('issue_critical_days', (string) config('app.issue_critical_days', 7)), $staleDays);
        $needsAttentionStartDays = $staleDays + max((int) floor(max($criticalDays - $staleDays, 1) / 2), 1);
        $needsAttentionStartDays = min($needsAttentionStartDays, $criticalDays);
        $statusIssues = collect(['inprogress', 'todo', 'done'])->mapWithKeys(function (string $status) {
            $query = Issue::query()
                ->with(['project:id,name,client_id', 'project.client:id,name', 'images', 'tags'])
                ->withCount(['subIssues', 'images'])
                ->whereNull('parent_id')
                ->where('status', $status);

            if ($status === 'done') {
                $query->orderByDesc('updated_at');
            } else {
                $query->latest();
            }

            return [$status => $query->limit(12)->get()];
        });

        $weekly = collect(range(7, 0))->map(function (int $offset) {
            $start = Carbon::now()->startOfWeek()->subWeeks($offset);
            $end = (clone $start)->endOfWeek();

            return [
                'label' => $start->format('M d'),
                'created' => Issue::query()->whereBetween('created_at', [$start, $end])->count(),
                'completed' => Issue::query()->whereBetween('done_at', [$start, $end])->count(),
            ];
        })->values();

        $monthly = collect(range(5, 0))->map(function (int $offset) {
            $start = Carbon::now()->startOfMonth()->subMonths($offset);
            $end = (clone $start)->endOfMonth();

            return [
                'label' => $start->format('M Y'),
                'created' => Issue::query()->whereBetween('created_at', [$start, $end])->count(),
                'completed' => Issue::query()->whereBetween('done_at', [$start, $end])->count(),
            ];
        })->values();

        $pendingWatch = Issue::query()
            ->whereNull('done_at')
            ->where('status', '!=', 'done')
            ->where('updated_at', '<=', Carbon::now()->subDays($staleDays))
            ->where('updated_at', '>', Carbon::now()->subDays($needsAttentionStartDays))
            ->count();

        $pendingNeedsAttention = Issue::query()
            ->whereNull('done_at')
            ->where('status', '!=', 'done')
            ->where('updated_at', '<=', Carbon::now()->subDays($needsAttentionStartDays))
            ->where('updated_at', '>', Carbon::now()->subDays($criticalDays))
            ->count();

        $pendingCritical = Issue::query()
            ->whereNull('done_at')
            ->where('status', '!=', 'done')
            ->where('updated_at', '<=', Carbon::now()->subDays($criticalDays))
            ->count();

        $pendingFocusIssues = Issue::query()
            ->with(['project:id,name,client_id', 'project.client:id,name', 'tags'])
            ->whereNull('done_at')
            ->where('status', '!=', 'done')
            ->where('updated_at', '<=', Carbon::now()->subDays($staleDays))
            ->orderBy('updated_at')
            ->limit(6)
            ->get();

        return Inertia::render('Dashboard', [
            'counts' => [
                'clients' => Client::query()->count(),
                'projects' => Project::query()->count(),
                'issues' => Issue::query()->count(),
            ],
            'statusIssues' => $statusIssues,
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
            'breadcrumbs' => [
                ['label' => 'Home'],
            ],
            'authUser' => $request->user(),
        ]);
    })->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::match(['put', 'post'], '/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::resource('clients', ClientController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::get('/issues/daily-activity', [IssueController::class, 'dailyActivity'])->name('issues.daily-activity');
    Route::resource('issues', IssueController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::get('/kanban', [IssueController::class, 'kanban'])->name('kanban');
    Route::delete('/issues/images/{issueImage}', [IssueController::class, 'destroyImage'])->name('issues.images.destroy');
    Route::delete('/issues/files/{issueFile}', [IssueController::class, 'destroyFile'])->name('issues.files.destroy');
    Route::delete('/issues/links/{issueLink}', [IssueController::class, 'destroyLink'])->name('issues.links.destroy');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function (): void {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/settings', [SiteSettingsController::class, 'index'])->name('settings.index');
        Route::match(['put', 'post'], '/settings', [SiteSettingsController::class, 'update'])->name('settings.update');
    });
});
