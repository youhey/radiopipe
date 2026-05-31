<?php

use App\Http\Controllers\Api\EpisodeController;
use App\Http\Controllers\Api\TopicRatingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'abilities:episodes:read'])
    ->prefix('episodes')
    ->name('api.episodes.')
    ->group(function (): void {
        Route::get('/', [EpisodeController::class, 'index'])->name('index');
        Route::get('/latest', [EpisodeController::class, 'latest'])->name('latest');
        Route::get('/{episode_key}', [EpisodeController::class, 'show'])->name('show');
    });

Route::middleware(['auth:sanctum', 'abilities:topics:rate'])
    ->prefix('topics')
    ->name('api.topics.')
    ->group(function (): void {
        Route::put('/{id}/rating', [TopicRatingController::class, 'update'])->name('rating.update');
        Route::delete('/{id}/rating', [TopicRatingController::class, 'destroy'])->name('rating.destroy');
    });
