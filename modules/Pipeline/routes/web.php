<?php

use Deally\Pipeline\Http\Controllers\PipelineController;
use Illuminate\Support\Facades\Route;

Route::middleware('deally')
    ->prefix('app')
    ->name('deally.')
    ->group(function (): void {
        Route::get('pipeline', [PipelineController::class, 'index'])->name('pipeline');
        Route::post('pipeline', [PipelineController::class, 'store'])->name('pipeline.store');
    });
