<?php

use App\Http\Controllers\Auth\GoogleOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])
    ->name('auth.google.callback');
