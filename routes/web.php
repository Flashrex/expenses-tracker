<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route('overview'))->name('home');
    Route::view('/overview', 'pages.overview')->name('overview');
    Route::view('/trends', 'pages.trends')->name('trends');
    Route::view('/upload', 'pages.upload')->name('upload');
});
