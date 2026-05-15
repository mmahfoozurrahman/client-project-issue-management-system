<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::match(['put', 'post'], '/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::resource('clients', ClientController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::prefix('projects/{project}/members')->name('project-members.')->group(function (): void {
        Route::post('/', [ProjectMemberController::class, 'store'])->name('store');
        Route::put('/{member}', [ProjectMemberController::class, 'update'])->name('update');
        Route::delete('/{member}', [ProjectMemberController::class, 'destroy'])->name('destroy');
        Route::get('/search', [ProjectMemberController::class, 'searchUsers'])->name('search');
    });

    Route::get('/issues/daily-activity', [IssueController::class, 'dailyActivity'])->name('issues.daily-activity');
    Route::resource('issues', IssueController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::get('/kanban', [IssueController::class, 'kanban'])->name('kanban');
    Route::delete('/issues/images/{issueImage}', [IssueController::class, 'destroyImage'])->name('issues.images.destroy');
    Route::delete('/issues/files/{issueFile}', [IssueController::class, 'destroyFile'])->name('issues.files.destroy');
    Route::delete('/issues/links/{issueLink}', [IssueController::class, 'destroyLink'])->name('issues.links.destroy');
    Route::patch('/issues/{issue}/status', [IssueController::class, 'updateStatus'])->name('issues.status.update');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function (): void {

        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/settings', [SiteSettingsController::class, 'index'])->name('settings.index');
        Route::match(['put', 'post'], '/settings', [SiteSettingsController::class, 'update'])->name('settings.update');

        Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('permissions', PermissionController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'update'])->name('roles.permissions.update');
    });
});
