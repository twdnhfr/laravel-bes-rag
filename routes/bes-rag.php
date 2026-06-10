<?php

use Illuminate\Support\Facades\Route;
use Twdnhfr\BesRag\Http\Controllers\DeepAnswerController;

Route::prefix(config('bes-rag.routes.prefix', 'bes-rag'))
    ->middleware(config('bes-rag.routes.middleware', ['api']))
    ->name('bes-rag.')
    ->group(function () {
        Route::post('/deep-answer', [DeepAnswerController::class, 'store'])->name('deep-answer');
        Route::get('/runs/{run}', [DeepAnswerController::class, 'show'])->name('runs.show');
        Route::get('/runs/{run}/debug', [DeepAnswerController::class, 'debug'])->name('runs.debug');
        Route::get('/runs/{run}/stream', [DeepAnswerController::class, 'stream'])->name('runs.stream');
    });
