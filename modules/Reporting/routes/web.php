<?php

use Deally\Reporting\Http\Controllers\ReportingController;
use Illuminate\Support\Facades\Route;

Route::middleware('deally')
    ->prefix('app')
    ->name('deally.')
    ->group(function (): void {
        Route::get('reporting', [ReportingController::class, 'teamPerformance'])->name('reporting');
        Route::get('reporting/account/{company}', [ReportingController::class, 'accountStory'])->name('reporting.account');
        Route::get('reporting/coaching/{call}', [ReportingController::class, 'coachingReview'])->name('reporting.coaching');
        Route::get('reporting/tasks', [ReportingController::class, 'teamTasks'])->name('reporting.tasks');
    });
