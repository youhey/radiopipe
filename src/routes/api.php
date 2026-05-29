<?php

use App\Http\Controllers\Api\EpisodesIndexController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'abilities:episodes:read'])
    ->get('/episodes', EpisodesIndexController::class)
    ->name('api.episodes.index');
