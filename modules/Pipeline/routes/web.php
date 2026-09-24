<?php

use Deally\Pipeline\Http\Controllers\ContactController;
use Deally\Pipeline\Http\Controllers\CustomerController;
use Deally\Pipeline\Http\Controllers\DealController;
use Deally\Pipeline\Http\Controllers\PipelineController;
use Deally\Pipeline\Http\Controllers\ServiceReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('deally')
    ->prefix('app')
    ->name('deally.')
    ->group(function (): void {
        Route::get('pipeline', [PipelineController::class, 'index'])->name('pipeline');
        Route::post('pipeline', [PipelineController::class, 'store'])->name('pipeline.store');

        Route::get('deals/{opportunity}', [DealController::class, 'show'])->name('deals.show');
        Route::patch('deals/{opportunity}/stage', [DealController::class, 'updateStage'])->name('deals.stage');
        Route::post('deals/{opportunity}/notes', [DealController::class, 'storeNote'])->name('deals.notes');

        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('customers/{customer}/contacts', [ContactController::class, 'store'])->name('contacts.store');

        Route::post('customers/{customer}/service-review', [ServiceReviewController::class, 'setup'])->name('service-reviews.setup');

        // The literal "sessions" segment must be matched before the
        // parameterised {schedule} routes below.
        Route::patch('service-reviews/sessions/{session}', [ServiceReviewController::class, 'rescheduleSession'])->name('service-reviews.sessions.reschedule');
        Route::post('service-reviews/sessions/{session}/hold', [ServiceReviewController::class, 'holdSession'])->name('service-reviews.sessions.hold');
        Route::post('service-reviews/sessions/{session}/cancel', [ServiceReviewController::class, 'cancelSession'])->name('service-reviews.sessions.cancel');

        Route::patch('service-reviews/{schedule}/cadence', [ServiceReviewController::class, 'updateCadence'])->name('service-reviews.cadence');
        Route::post('service-reviews/{schedule}/sessions', [ServiceReviewController::class, 'catchUp'])->name('service-reviews.sessions.store');
        Route::post('service-reviews/{schedule}/end', [ServiceReviewController::class, 'end'])->name('service-reviews.end');
    });
