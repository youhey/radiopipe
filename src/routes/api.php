<?php

use App\Http\Controllers\Api\EpisodeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'abilities:episodes:read'])
    ->prefix('episodes')
    ->name('api.episodes.')
    ->group(function (): void {
        Route::get('/', [EpisodeController::class, 'index'])->name('index');
        Route::get('/latest', [EpisodeController::class, 'latest'])->name('latest');
        Route::get('/{episode_key}', [EpisodeController::class, 'show'])->name('show');
    });
