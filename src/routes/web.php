<?php

use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\Auth\LocalAdminLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])
    ->name('auth.google.callback');

Route::get('/_local/admin/login', LocalAdminLoginController::class)
    ->name('local.admin.login');
