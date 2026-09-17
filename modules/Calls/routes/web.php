<?php

use Deally\Calls\Http\Controllers\CallController;
use Illuminate\Support\Facades\Route;

Route::middleware('deally')
    ->prefix('app')
    ->name('deally.')
    ->group(function (): void {
        Route::get('calls', [CallController::class, 'index'])->name('calls.index');
        Route::post('calls', [CallController::class, 'store'])->name('calls.store');
        Route::get('calls/{call}/live', [CallController::class, 'live'])->name('calls.live');
        Route::post('calls/{call}/live/transcribe', [CallController::class, 'liveTranscribe'])->name('calls.live.transcribe');
        Route::post('calls/{call}/live/query', [CallController::class, 'liveQuery'])->name('calls.live.query');
        Route::get('calls/{call}/summary', [CallController::class, 'summary'])->name('calls.summary');
        Route::get('calls/{call}', [CallController::class, 'show'])->name('calls.show');
        Route::post('calls/{call}/end', [CallController::class, 'end'])->name('calls.end');
        Route::post('calls/{call}/transcript', [CallController::class, 'transcript'])->name('calls.transcript');
        Route::post('calls/{call}/flag', [CallController::class, 'flag'])->name('calls.flag');
    });
