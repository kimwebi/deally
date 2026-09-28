<?php

use Deally\Calls\Http\Controllers\CallController;
use Illuminate\Support\Facades\Route;

Route::middleware('deally')
    ->prefix('app')
    ->name('deally.')
    ->group(function (): void {
        Route::get('calls', [CallController::class, 'index'])->name('calls.index');
        Route::post('calls', [CallController::class, 'store'])->name('calls.store');
        Route::post('calls/{call}/invitations', [CallController::class, 'invite'])->name('calls.invite');
        Route::get('calls/{call}/invitations/preview', [CallController::class, 'invitePreview'])->name('calls.invite.preview');
        Route::post('calls/{call}/join', [CallController::class, 'join'])->name('calls.join');
        Route::post('calls/{call}/fail', [CallController::class, 'fail'])->name('calls.fail');
        Route::post('calls/{call}/corrections', [CallController::class, 'correct'])->name('calls.correct');
        Route::post('calls/{call}/flags', [CallController::class, 'storeFlag'])->name('calls.flags.store');
        Route::post('calls/{call}/flags/{flag}/resolve', [CallController::class, 'resolveFlag'])->name('calls.flags.resolve');
        Route::post('calls/{call}/objections', [CallController::class, 'objection'])->name('calls.objections.store');
        Route::post('calls/{call}/proposal', [CallController::class, 'proposal'])->name('calls.proposal');
        Route::get('calls/{call}/live', [CallController::class, 'live'])->name('calls.live');
        Route::post('calls/{call}/live/start', [CallController::class, 'liveStart'])->name('calls.live.start');
        Route::post('calls/{call}/live/transcribe', [CallController::class, 'liveTranscribe'])->name('calls.live.transcribe');
        Route::post('calls/{call}/live/findings/{finding}/feedback', [CallController::class, 'liveFindingFeedback'])->name('calls.live.finding.feedback');
        Route::post('calls/{call}/live/query', [CallController::class, 'liveQuery'])->name('calls.live.query');
        Route::get('calls/{call}/summary', [CallController::class, 'summary'])->name('calls.summary');
        Route::get('calls/{call}/review', [CallController::class, 'review'])->name('calls.review');
        Route::get('calls/{call}/recordings/{recording}', [CallController::class, 'recording'])->name('calls.recording');
        Route::get('calls/{call}/transcript/download', [CallController::class, 'downloadTranscript'])->name('calls.transcript.download');
        Route::get('calls/{call}/detail', [CallController::class, 'detail'])->name('calls.detail');
        Route::get('calls/{call}', [CallController::class, 'show'])->name('calls.show');
        Route::post('calls/{call}/end', [CallController::class, 'end'])->name('calls.end');
        Route::post('calls/{call}/transcript', [CallController::class, 'transcript'])->name('calls.transcript');
        Route::post('calls/{call}/flag', [CallController::class, 'flag'])->name('calls.flag');
        Route::post('ask', [CallController::class, 'ask'])->name('ask');
    });
