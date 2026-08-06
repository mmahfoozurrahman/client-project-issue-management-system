<?php

use App\Http\Controllers\Api\IntegrationIssueController;
use Illuminate\Support\Facades\Route;

Route::prefix('integrations')
    ->middleware('issue.integration')
    ->group(function (): void {
        Route::post('/issues', [IntegrationIssueController::class, 'store'])
            ->name('api.integrations.issues.store');
    });
