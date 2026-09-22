<?php

use Deally\Solutions\Http\Controllers\SolutionsController;
use Illuminate\Support\Facades\Route;

Route::middleware('deally')
    ->prefix('app')
    ->name('deally.')
    ->group(function (): void {
        Route::get('solutions', [SolutionsController::class, 'workspace'])->name('solutions.index');
        Route::post('solutions/gaps/{gap}', [SolutionsController::class, 'resolveGap'])->name('solutions.gaps.resolve');
    });
