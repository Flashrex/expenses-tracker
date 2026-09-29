<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\OverviewEntriesController;
use App\Http\Controllers\StatementUploadController;
use App\Http\Controllers\TrendsController;
use App\Http\Middleware\DiscardPendingStatementImport;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', DiscardPendingStatementImport::class])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route('overview'))->name('home');
    Route::get('/overview', OverviewController::class)->name('overview');
    Route::get('/overview/entries', OverviewEntriesController::class)->name('overview.entries');
    Route::get('/trends', TrendsController::class)->name('trends');

    Route::get('/upload', [StatementUploadController::class, 'create'])->name('upload');
    Route::post('/upload', [StatementUploadController::class, 'store'])->name('upload.store');
    Route::get('/upload/review', [StatementUploadController::class, 'review'])->name('upload.review');
    Route::post('/upload/confirm', [StatementUploadController::class, 'confirm'])->name('upload.confirm');
    Route::post('/upload/discard', [StatementUploadController::class, 'discard'])->name('upload.discard');
    Route::post('/upload/assign', [StatementUploadController::class, 'assign'])->name('upload.assign');
    Route::post('/upload/always', [StatementUploadController::class, 'always'])->name('upload.always');
});
