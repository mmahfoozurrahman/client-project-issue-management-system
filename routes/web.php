<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ProjectController;
use App\Models\Client;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', function (Request $request) {
        return Inertia::render('Dashboard', [
            'counts' => [
                'clients' => Client::query()->count(),
                'projects' => Project::query()->count(),
                'issues' => Issue::query()->count(),
            ],
            'recentIssues' => Issue::query()
                ->with(['project:id,name,client_id', 'project.client:id,name', 'images'])
                ->withCount(['subIssues', 'images'])
                ->whereNull('parent_id')
                ->latest()
                ->take(6)
                ->get(),
            'breadcrumbs' => [
                ['label' => 'Home'],
            ],
            'authUser' => $request->user(),
        ]);
    })->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::resource('clients', ClientController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::resource('issues', IssueController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::get('/kanban', [IssueController::class, 'kanban'])->name('kanban');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function (): void {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});
