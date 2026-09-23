<?php

use Deally\Proposals\Http\Controllers\KnowledgeBaseController;
use Deally\Proposals\Http\Controllers\ProposalController;
use Illuminate\Support\Facades\Route;

Route::middleware('deally')
    ->prefix('app')
    ->name('deally.')
    ->group(function (): void {
        Route::get('proposals', [ProposalController::class, 'index'])->name('proposals.index');
        Route::post('proposals', [ProposalController::class, 'store'])->name('proposals.store');
        Route::post('proposals/{proposal}/status', [ProposalController::class, 'updateStatus'])->name('proposals.status');

        Route::get('kb', [KnowledgeBaseController::class, 'index'])->name('kb.index');
        Route::post('kb', [KnowledgeBaseController::class, 'storeEntry'])->name('kb.store');
    });
