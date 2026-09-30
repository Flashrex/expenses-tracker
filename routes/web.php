<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ManualRuleController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\OverviewEntriesController;
use App\Http\Controllers\RuleRerunController;
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
    Route::get('/upload/review/{period?}', [StatementUploadController::class, 'review'])->where('period', '[0-9]{4}-[0-9]{2}')->name('upload.review');
    Route::post('/upload/review/{period}/confirm', [StatementUploadController::class, 'confirm'])->where('period', '[0-9]{4}-[0-9]{2}')->name('upload.confirm');
    Route::post('/upload/review/{period}/skip', [StatementUploadController::class, 'skip'])->where('period', '[0-9]{4}-[0-9]{2}')->name('upload.skip');
    Route::post('/upload/review/{period}/assign', [StatementUploadController::class, 'assign'])->where('period', '[0-9]{4}-[0-9]{2}')->name('upload.assign');
    Route::post('/upload/review/{period}/always', [StatementUploadController::class, 'always'])->where('period', '[0-9]{4}-[0-9]{2}')->name('upload.always');
    Route::post('/upload/review/{period}/override', [StatementUploadController::class, 'override'])->where('period', '[0-9]{4}-[0-9]{2}')->name('upload.override');
    Route::post('/upload/discard', [StatementUploadController::class, 'discard'])->name('upload.discard');
    Route::post('/upload/notice/dismiss', [StatementUploadController::class, 'dismissNotice'])->name('upload.notice.dismiss');

    Route::get('/groups', [GroupController::class, 'index'])->name('groups');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::put('/groups/order', [GroupController::class, 'order'])->name('groups.order');
    Route::put('/groups/ignored', [GroupController::class, 'updateIgnored'])->name('groups.ignored.update');
    Route::post('/groups/rerun', [RuleRerunController::class, 'store'])->name('groups.rerun.store');
    Route::get('/groups/rerun', [RuleRerunController::class, 'show'])->name('groups.rerun');
    Route::post('/groups/rerun/decide', [RuleRerunController::class, 'decide'])->name('groups.rerun.decide');
    Route::post('/groups/rerun/apply', [RuleRerunController::class, 'apply'])->name('groups.rerun.apply');
    Route::post('/groups/rerun/cancel', [RuleRerunController::class, 'cancel'])->name('groups.rerun.cancel');
    Route::put('/groups/{group:key}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group:key}', [GroupController::class, 'destroy'])->name('groups.destroy');
    Route::delete('/rules/{rule}', [ManualRuleController::class, 'destroy'])->name('rules.destroy');
});
